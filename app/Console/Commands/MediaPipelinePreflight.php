<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaPipelinePreflight extends Command
{
    protected $signature = 'gomovie:media-preflight {--json : Emit machine-readable status}';
    protected $description = 'Read-only media pipeline readiness checks; does not encode or publish content';

    public function handle(): int
    {
        $localRoot = storage_path('app/private');
        $ffmpeg = (string) config('gomovie.ffmpeg_binary', '/usr/bin/ffmpeg');
        $ffprobe = (string) config('gomovie.ffprobe_binary', '/usr/bin/ffprobe');
        $checks = [
            'transcoding_enabled' => config('gomovie.transcoding_enabled') === true,
            'private_storage_writable' => is_dir($localRoot) && is_writable($localRoot),
            'ffmpeg_available' => str_starts_with($ffmpeg, '/') && is_file($ffmpeg) && is_executable($ffmpeg),
            'ffprobe_available' => str_starts_with($ffprobe, '/') && is_file($ffprobe) && is_executable($ffprobe),
            'edge_enabled' => config('gomovie.edge_enabled') === true,
            'media_edge_https' => str_starts_with((string) config('gomovie.media_edge_url'), 'https://'),
            'media_signing_key_configured' => strlen((string) config('gomovie.media_signing_key')) >= 32,
            'edge_shared_key_configured' => strlen((string) config('gomovie.edge_shared_key')) >= 32,
            'billing_disabled' => config('gomovie.billing_enabled') !== true,
        ];
        $ready = !in_array(false, $checks, true);
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $ready, 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $status) {
                $this->line(($status ? '[OK] ' : '[BLOCKED] ').str_replace('_',' ', $name));
            }
            $this->line($ready ? 'Configured; independent media acceptance is still required.'
                : 'Not configured for live media processing (expected for development).');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
