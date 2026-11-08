<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class TranscodingService
{
    /**
     * Encode explicitly vetted content into PRIVATE local HLS renditions.
     * Never uploads to the CDN or creates a playable/ready media asset.
     */
    public function encode(int $ingestId): string
    {
        $ingest = app(TranscodeGate::class)->validate($ingestId);
        abort_unless($ingest->disk === 'local'
            && is_string($ingest->object_key)
            && preg_match('#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.(mp4|mov|mkv|webm)\z#i',
                $ingest->object_key), 422, 'Invalid private media source.');

        $disk = Storage::disk('local');
        $root = realpath(storage_path('app/private'));
        $source = realpath($disk->path($ingest->object_key));
        abort_unless($root && $source && is_file($source)
            && str_starts_with($source, $root.DIRECTORY_SEPARATOR), 422,
            'Source is outside private storage or is unavailable.');
        $actualHash = hash_file('sha256', $source);
        abort_unless($actualHash && hash_equals((string)$ingest->sha256, $actualHash),
            422, 'Original source integrity verification failed.');

        $ffmpeg = $this->binary((string) config('gomovie.ffmpeg_binary'));
        $ffprobe = $this->binary((string) config('gomovie.ffprobe_binary'));

        $probe = new Process([
            $ffprobe, '-v','error', '-show_entries',
            'stream=codec_type,width,height:format=duration', '-of','json', $source,
        ]);
        $probe->setTimeout(60);
        $probe->run();
        if (!$probe->isSuccessful()) {
            throw new RuntimeException('Video probing failed; check secured operator logs.');
        }
        $metadata = json_decode($probe->getOutput(), true);
        $video = collect($metadata['streams'] ?? [])
            ->first(fn ($entry) => ($entry['codec_type'] ?? null) === 'video');
        $height = (int) ($video['height'] ?? 0);
        $duration = (float) ($metadata['format']['duration'] ?? 0);
        abort_unless($height >= 240 && $height <= 16384 && is_finite($duration)
            && $duration > 0 && $duration <= 21600, 422, 'Unsupported video dimensions or duration.');

        $tiers = [
            1080 => ['rate'=>'5000k','buf'=>'10000k','bandwidth'=>5500000,'audio'=>'160k'],
            720 => ['rate'=>'2800k','buf'=>'5600k','bandwidth'=>3200000,'audio'=>'128k'],
            480 => ['rate'=>'1400k','buf'=>'2800k','bandwidth'=>1700000,'audio'=>'96k'],
        ];
        $tiers = array_filter($tiers, fn ($tier) => $tier <= $height, ARRAY_FILTER_USE_KEY);
        if (!$tiers) {
            $tier = (int) (floor($height / 2) * 2);
            $tiers[$tier] = ['rate'=>'800k','buf'=>'1600k','bandwidth'=>950000,'audio'=>'64k'];
        }

        $claimed = DB::table('media_ingests')->where('id',$ingestId)
            ->where('status','approved')->update([
                'status'=>'processing', 'pipeline_error'=>null, 'updated_at'=>now(),
            ]);
        abort_unless($claimed === 1, 409, 'Media is no longer approved for encoding.');

        $prefix = 'transcodes/staging/'.$ingestId.'/'.(string) Str::uuid();
        $master = "#EXTM3U\n#EXT-X-VERSION:3\n";
        try {
            if (!$disk->makeDirectory($prefix)) throw new RuntimeException('Private workspace unavailable.');
            foreach ($tiers as $tier => $settings) {
                $directory = $prefix.'/'.$tier.'p';
                if (!$disk->makeDirectory($directory)) throw new RuntimeException('Rendition workspace unavailable.');
                $segment = $disk->path($directory.'/segment_%05d.ts');
                $playlist = $disk->path($directory.'/index.m3u8');
                $encoding = new Process([
                    $ffmpeg, '-hide_banner','-loglevel','error','-nostdin','-y',
                    '-i',$source, '-map','0:v:0','-map','0:a:0?',
                    '-vf','scale=-2:'.$tier.':force_original_aspect_ratio=decrease:force_divisible_by=2',
                    '-c:v','libx264','-preset','veryfast','-pix_fmt','yuv420p',
                    '-crf','23','-maxrate',$settings['rate'],'-bufsize',$settings['buf'],
                    '-c:a','aac','-b:a',$settings['audio'],'-ac','2',
                    '-f','hls','-hls_time','6','-hls_playlist_type','vod',
                    '-hls_segment_filename',$segment,$playlist,
                ]);
                $encoding->setTimeout(7200);
                $encoding->run();
                if (!$encoding->isSuccessful()) throw new RuntimeException('FFmpeg encoding failed.');
                $segments = array_filter($disk->files($directory),
                    fn ($path) => str_ends_with($path, '.ts'));
                if (!$disk->exists($directory.'/index.m3u8') || count($segments) === 0) {
                    throw new RuntimeException('Encoded rendition did not pass file checks.');
                }
                $master .= '#EXT-X-STREAM-INF:BANDWIDTH='.$settings['bandwidth']."\n"
                    .$tier."p/index.m3u8\n";
            }
            $manifest = $prefix.'/master.m3u8';
            if (!$disk->put($manifest, $master)) throw new RuntimeException('Private manifest creation failed.');
            DB::table('media_ingests')->where('id',$ingestId)
                ->where('status','processing')->update([
                    'status'=>'encoded','encoded_manifest_key'=>$manifest,
                    'encoded_at'=>now(),'updated_at'=>now(),
                ]);
            return $manifest;
        } catch (Throwable $error) {
            $disk->deleteDirectory($prefix);
            DB::table('media_ingests')->where('id',$ingestId)
                ->where('status','processing')->update([
                    'status'=>'failed','pipeline_error'=>'encoding_failed',
                    'updated_at'=>now(),
                ]);
            report($error);
            throw new RuntimeException('Private media encoding failed. Check protected logs.', 0, $error);
        }
    }

    private function binary(string $path): string
    {
        abort_unless(str_starts_with($path,'/') && is_file($path) && is_executable($path),
            503, 'Trusted FFmpeg or FFprobe binary unavailable.');
        return $path;
    }
}
