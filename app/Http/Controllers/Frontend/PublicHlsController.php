<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Rights\GlobalRights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class PublicHlsController extends Controller
{
    /**
     * Only verified, published, free media assets can be streamed. The original
     * quarantined video and any unrelated private files are never reachable.
     */
    public function __invoke(Request $request, int $asset, string $path, AutomaticPublicationService $auto): Response
    {
        abort_unless($request->isMethod('GET') && $auto->publicEnabled(), 404);
        abort_unless((bool) preg_match(
            '#\A(?:master\.m3u8|[0-9]{3,4}p/(?:index\.m3u8|segment_[0-9]{5}\.ts))\z#D', $path
        ), 404);
        $record = DB::table('media_assets')->where('id', $asset)
            ->where('status', 'ready')->where('format', 'hls')
            ->where('storage_disk', 'local')->whereNull('drm_scheme')->first();
        abort_unless($record && in_array($record->streamable_type, ['movie', 'episode'], true), 404);
        $ready = $auto->readyAsset($record->streamable_type, (int) $record->streamable_id);
        // Check this specific asset; an older unreleased version must not become
        // playable merely because a different version of the title is ready.
        abort_unless($ready && (int) $ready->id === $asset, 404);
        $file = DB::table('public_media_files')->where('media_asset_id', $asset)
            ->where('relative_path', $path)->first();
        abort_unless($file, 404);
        $manifest = (string) $record->manifest_key;
        abort_unless((bool) preg_match('#\Atranscodes/staging/[0-9]+/[0-9a-f-]{36}/master\.m3u8\z#iD', $manifest), 404);
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $candidate = $disk->path(dirname($manifest).'/'.$path);
        $real = realpath($candidate);
        abort_unless($root && $real && !is_link($candidate)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real) && is_readable($real)
            && filesize($real) === (int) $file->size_bytes
            && hash_equals((string) $file->sha256, (string) hash_file('sha256', $real)),
            404, 'Public media file unavailable.');
        $type = str_ends_with($path, '.m3u8')
            ? 'application/vnd.apple.mpegurl' : 'video/mp2t';

        return response()->file($real, [
            'Content-Type' => $type,
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
