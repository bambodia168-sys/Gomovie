<?php

namespace App\Services\Media;

use App\Support\SiteSettings;
use App\Support\VideoUploadLimit;
use App\Jobs\MirrorPrivateIngestToR2;
use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Movie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The only intake entrypoint for uploaded video. Uploads always enter a
 * private LOCAL quarantine first, even when optional R2 mirroring is enabled.
 * Nothing here marks a title approved, encoded, licensed or playable.
 */
final class PrivateVideoIntake
{
    public function store(
        string $kind,
        int $titleId,
        UploadedFile $video,
        int $uploaderId,
        ?string $ip,
        bool $autoPublish = true,
        bool $publishWatchPage = false,
    ): int {
        abort_unless(in_array($kind, ['movie', 'episode'], true), 422);
        $extension = match ($video->getMimeType()) {
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-matroska' => 'mkv',
            'video/webm' => 'webm',
            default => null,
        };
        abort_unless($extension !== null && $video->isValid(), 422, 'Unsupported video file.');

        $source = $video->getRealPath();
        abort_unless(is_string($source) && is_file($source), 422, 'Uploaded video is unavailable.');
        $maxBytes = VideoUploadLimit::bytes();
        $sourceSize = filesize($source);
        abort_unless($sourceSize && $sourceSize <= $maxBytes, 422, 'The video exceeds the upload limit or is empty.');
        $hash = hash_file('sha256', $source);
        abort_unless(is_string($hash), 422, 'Unable to fingerprint the video.');

        $path = 'ingests/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        $stored = $video->storeAs(dirname($path), basename($path), 'local');
        abort_unless($stored === $path, 503, 'Private upload failed.');

        // Trust the actual bytes written to private storage, not only client
        // or uploaded-file size metadata (which can be inconsistent).
        $storedPath = $disk->path($path);
        $actualSize = is_file($storedPath) ? filesize($storedPath) : false;
        $storedHash = is_file($storedPath) ? hash_file('sha256', $storedPath) : false;
        if (!is_int($actualSize) || $actualSize < 1 || $actualSize > $maxBytes
            || !is_string($storedHash) || !hash_equals($hash, $storedHash)) {
            $disk->delete($path);
            abort(422, 'The private upload failed its integrity check.');
        }

        $r2Enabled = SiteSettings::get('r2_enabled') === true
            && is_string(SiteSettings::get('r2_bucket')) && SiteSettings::get('r2_bucket') !== ''
            && preg_match(SiteSettings::R2_ENDPOINT, (string) SiteSettings::get('r2_endpoint'))
            && filled(SiteSettings::get('r2_key'))
            && filled(SiteSettings::get('r2_secret'));

        $autoPublish = $autoPublish && app(AutomaticPublicationService::class)->enabled();

        try {
            $id = DB::transaction(function () use ($kind, $titleId, $uploaderId, $video, $ip, $path, $hash, $actualSize, $r2Enabled, $publishWatchPage, $autoPublish): int {
                $id = DB::table('media_ingests')->insertGetId([
                    'ingestable_type' => $kind,
                    'ingestable_id' => $titleId,
                    'uploaded_by' => $uploaderId,
                    'disk' => 'local',
                    'object_key' => $path,
                    'original_name' => Str::limit($video->getClientOriginalName(), 255, ''),
                    'mime' => $video->getMimeType(),
                    'size_bytes' => $actualSize,
                    'sha256' => $hash,
                    'status' => 'quarantined',
                    'auto_publication_requested' => $autoPublish,
                    'cloud_status' => $r2Enabled ? 'pending' : 'local_only',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('admin_audit_events')->insert([
                    'actor_id' => $uploaderId,
                    'action' => 'media.private_ingest_staged',
                    'resource_type' => 'media_ingest',
                    'resource_id' => $id,
                    'details' => json_encode(['size_bytes' => $actualSize, 'cloud_mirror_queued' => $r2Enabled], JSON_THROW_ON_ERROR),
                    'ip_address' => $ip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($publishWatchPage) {
                    $updated = Movie::query()->whereKey($titleId)->where('status', 'draft')
                        ->where('is_premium', false)->where('is_standalone_video', true)
                        ->update(['status' => 'published', 'published_at' => now()]);
                    abort_unless($kind === 'movie' && $updated === 1, 409,
                        'The public video title changed during upload.');
                }

                return (int) $id;
            });
        } catch (Throwable $error) {
            $disk->delete($path);
            throw $error;
        }

        // The uploaded local source is always available even if optional
        // external storage or a background worker is temporarily unavailable.
        if ($r2Enabled) {
            try {
                MirrorPrivateIngestToR2::dispatch($id);
            } catch (Throwable $error) {
                report($error);
                DB::table('media_ingests')->where('id', $id)
                    ->update(['cloud_status' => 'failed', 'updated_at' => now()]);
            }
        }

        if ($autoPublish) {
            try {
                AutoPublishUploadedVideo::dispatch($id);
            } catch (Throwable $error) {
                report($error);
                DB::table('media_ingests')->where('id', $id)->update([
                    'pipeline_error' => 'publication_queue_unavailable', 'updated_at' => now(),
                ]);
            }
        }

        return $id;
    }
}
