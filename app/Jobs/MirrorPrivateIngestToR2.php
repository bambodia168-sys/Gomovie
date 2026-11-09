<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MirrorPrivateIngestToR2 implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 1800;
    public int $backoff = 60;

    public function __construct(public readonly int $ingestId)
    {
        $this->onConnection((string) config('gomovie.media_queue_connection', 'database_media'));
        $this->onQueue('gomovie-media');
    }

    public function handle(): void
    {
        abort_unless(config('gomovie.r2_enabled') === true, 503, 'Cloud storage is disabled.');
        abort_unless(preg_match('#\Ahttps://[a-f0-9]{32}\.r2\.cloudflarestorage\.com/?\z#D',
            (string) config('gomovie.r2_endpoint')), 503, 'Invalid R2 endpoint.');

        $ingest = DB::table('media_ingests')->where('id', $this->ingestId)->first();
        abort_unless($ingest, 404);
        if ($ingest->cloud_status === 'ready') return;

        abort_unless($ingest->disk === 'local' && in_array($ingest->status, [
            'quarantined', 'approved', 'encoded', 'rejected',
        ], true), 403, 'Ingest cannot be mirrored.');
        abort_unless(is_string($ingest->object_key) &&
            preg_match('#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.(mp4|mov|mkv|webm)\z#iD',
                $ingest->object_key), 422, 'Invalid private source key.');

        $local = Storage::disk('local');
        $candidate = $local->path($ingest->object_key);
        $real = realpath($candidate);
        $root = realpath(storage_path('app/private'));
        abort_unless($real && $root && !is_link($candidate)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real), 422, 'Source is not in private storage.');
        abort_unless(hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $real)),
            422, 'Source checksum mismatch.');

        $key = 'gomovie/quarantine/ingests/'.$ingest->id.'/'.basename($ingest->object_key);
        $stream = fopen($real, 'rb');
        if (!is_resource($stream)) throw new RuntimeException('Cannot read source file.');

        try {
            $cloud = Storage::disk('r2');
            if (!$cloud->put($key, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Private R2 mirror failed.');
            }
            if (!$cloud->exists($key)
                || $cloud->size($key) !== (int) $ingest->size_bytes) {
                throw new RuntimeException('Private R2 object integrity check failed.');
            }
            DB::table('media_ingests')->where('id', $ingest->id)->update([
                'cloud_disk' => 'r2', 'cloud_object_key' => $key,
                'cloud_status' => 'ready', 'cloud_mirrored_at' => now(), 'updated_at' => now(),
            ]);
        } finally {
            fclose($stream);
        }
    }

    public function failed(?Throwable $error): void
    {
        DB::table('media_ingests')->where('id', $this->ingestId)
            ->where('cloud_status', 'pending')
            ->update(['cloud_status' => 'failed', 'updated_at' => now()]);
    }
}
