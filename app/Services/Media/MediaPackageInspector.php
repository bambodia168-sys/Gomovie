<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fail-closed inspection of LOCAL HLS packages only. Clear HLS staging is not
 * DRM or legal approval; remote objects are never made playable by this class.
 */
final class MediaPackageInspector
{
    public function inspect(int $ingestId): array
    {
        $ingest = DB::table('media_ingests')->where('id', $ingestId)->first();
        abort_unless($ingest, 404);
        abort_unless($ingest->status === 'encoded'
            && $ingest->security_scan_status === 'clean'
            && $ingest->rights_review_status === 'verified'
            && $ingest->approved_at !== null && $ingest->approved_by !== null
            && in_array($ingest->ingestable_type, ['movie','episode'], true),
            403, 'The ingest is not independently approved and encoded.');

        // Recheck original quarantine bytes before trusting the derived package.
        abort_unless($ingest->disk === 'local' && is_string($ingest->object_key)
            && preg_match('#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.(mp4|mov|mkv|webm)\z#iD',
                $ingest->object_key), 422, 'Original private ingest path is invalid.');
        $sourcePath = Storage::disk('local')->path($ingest->object_key);
        $privateRoot = realpath(storage_path('app/private'));
        $source = realpath($sourcePath);
        abort_unless($source && $privateRoot && !is_link($sourcePath)
            && str_starts_with($source, $privateRoot.DIRECTORY_SEPARATOR)
            && is_file($source)
            && hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $source)),
            422, 'Original media integrity check failed.');

        $type = $ingest->ingestable_type;
        $id = (int) $ingest->ingestable_id;
        app(\App\Services\Rights\GlobalRights::class)->requireActive($type, $id);
        if ($type === 'movie') {
            abort_unless(DB::table('movies')->where('id', $id)
                ->where('status', 'published')->exists(), 403, 'Title is not published.');
        } else {
            abort_unless(DB::table('episodes')->join('seasons', 'seasons.id', '=', 'episodes.season_id')
                ->join('series', 'series.id', '=', 'seasons.series_id')
                ->where('episodes.id', $id)->where('episodes.status', 'published')
                ->where('series.status', 'published')->exists(), 403, 'Episode or series is not published.');
        }

        $key = $ingest->encoded_manifest_key;
        abort_unless(is_string($key)
            && preg_match('#\Atranscodes/staging/'.preg_quote((string) $ingestId, '#')
                .'/[0-9a-f-]{36}/master\.m3u8\z#D', $key)
            && $ingest->encoded_at !== null, 422, 'Invalid or missing private encoded manifest.');

        $root = dirname($key);
        $disk = Storage::disk('local');
        $realRoot = realpath($disk->path($root));
        $realPrivate = realpath(storage_path('app/private'));
        abort_unless($realRoot && $realPrivate && is_dir($realRoot)
            && str_starts_with($realRoot, $realPrivate.DIRECTORY_SEPARATOR), 422,
            'Encoded media storage is not private.');

        $files = [];
        $master = $this->safeFile($disk, $root, 'master.m3u8', $realRoot, $files, true);
        $variants = $this->references($master, true);
        abort_unless(count($variants) > 0 && count($variants) <= 5, 422,
            'Invalid variant count.');

        foreach ($variants as $variant) {
            abort_unless((bool) preg_match('#\A[0-9]{3,4}p/index\.m3u8\z#D', $variant), 422,
                'Invalid rendition path.');
            $body = $this->safeFile($disk, $root, $variant, $realRoot, $files, true);
            $segments = $this->references($body, false);
            abort_unless(str_contains($body, '#EXT-X-ENDLIST')
                && count($segments) > 0 && count($segments) <= 5000, 422,
                'Invalid VOD segment playlist.');
            foreach ($segments as $segment) {
                abort_unless((bool) preg_match('/\Asegment_[0-9]{5}\.ts\z/D', $segment), 422,
                    'Invalid private media segment.');
                $prefix = dirname($variant);
                $this->safeFile($disk, $root, $prefix.'/'.$segment, $realRoot, $files, false);
            }
        }

        abort_unless(count($files) <= 10000, 422, 'Media package exceeds file-count limit.');

        return [
            'ingest' => $ingest,
            'disk_root' => $root,
            'files' => $files,
            'file_count' => count($files),
            'manifest_sha256' => $files['master.m3u8']['sha256'],
        ];
    }

    private function safeFile($disk, string $root, string $relative, string $realRoot, array &$files, bool $playlist): string
    {
        abort_if(isset($files[$relative]), 422, 'Repeated HLS file reference.');
        $key = $root.'/'.$relative;
        $path = $disk->path($key);
        abort_unless(!is_link($path) && is_file($path), 422, 'Private HLS object missing.');
        $actual = realpath($path);
        abort_unless($actual && str_starts_with($actual, $realRoot.DIRECTORY_SEPARATOR),
            422, 'Unsafe HLS object path.');
        $bytes = filesize($actual);
        abort_unless(is_int($bytes) && $bytes > 0
            && (!$playlist || $bytes <= 131072), 422, 'Invalid HLS file size.');
        $hash = hash_file('sha256', $actual);
        abort_unless(is_string($hash), 422, 'Could not hash HLS file.');
        $files[$relative] = ['key'=>$key, 'sha256'=>$hash, 'bytes'=>$bytes];
        return $playlist ? (string) file_get_contents($actual) : '';
    }

    private function references(string $playlist, bool $master): array
    {
        abort_unless(str_starts_with($playlist, "#EXTM3U\n")
            || str_starts_with($playlist, "#EXTM3U\r\n"), 422, 'Invalid HLS header.');
        $items = [];
        $lines = preg_split('/\r?\n/', $playlist);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_starts_with($line, '#')) {
                // No cipher keys, relative URI tags, maps, LL-HLS, external
                // references or unknown extensions until independently audited.
                abort_unless((bool) preg_match('/\A#(?:EXTM3U|EXTINF(?::.*)?|EXT-X-(?:VERSION|TARGETDURATION|MEDIA-SEQUENCE|PLAYLIST-TYPE|ENDLIST|STREAM-INF)(?::.*)?)\z/D', $line)
                    && !str_contains($line, 'URI='), 422, 'Unsupported HLS directive.');
                continue;
            }
            abort_unless(count($items) < 5000, 422, 'Too many HLS references.');
            if ($master) {
                abort_unless(str_ends_with($line, '.m3u8'), 422, 'Unexpected master playlist reference.');
            } else {
                abort_unless(str_ends_with($line, '.ts'), 422, 'Unexpected rendition reference.');
            }
            $items[] = $line;
        }
        abort_unless(count($items) === count(array_unique($items)), 422,
            'Duplicate HLS file reference.');
        return $items;
    }
}
