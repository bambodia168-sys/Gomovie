<?php

namespace App\Services\Operations;

use App\Services\Media\PublicationRecovery;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Read-only operational diagnostics. No secrets, full logs or user data.
 * A healthy snapshot is NOT a production-release approval.
 */
final class OperationalSnapshot
{
    public function inspect(): array
    {
        $checks = [];
        $alerts = [];
        $metrics = [
            'pending_default_jobs'=>null,
            'pending_media_jobs'=>null,
            'failed_jobs'=>null,
            'active_stream_sessions'=>null,
            'quarantined_ingests'=>null,
            'failed_media_ingests'=>null,
        ];
        $probes = ['default'=>null,'gomovie-media'=>null];
        $mediaIssues = [];
        $mediaQueueWait = null;

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'healthy';
        } catch (Throwable $error) {
            report($error);
            $checks['database'] = 'unavailable';
            $alerts[] = 'Database connection failed.';
        }

        $tables = ['users','movies','series','media_ingests','content_rights',
            'stream_sessions','jobs','failed_jobs','queue_probe_runs','admin_audit_events'];
        if ($checks['database'] === 'healthy') {
            try {
                $missing = array_values(array_filter($tables, fn ($table) => !Schema::hasTable($table)));
                $checks['schema'] = count($missing) === 0 ? 'healthy' : 'incomplete';
                if ($missing) $alerts[] = 'Required application migrations are incomplete.';
                if (Schema::hasTable('jobs')) {
                    $metrics['pending_default_jobs'] = DB::table('jobs')->where('queue','default')->count();
                }
                $mediaQueue = config('queue.connections.'.config('gomovie.media_queue_connection'), []);
                if (($mediaQueue['driver'] ?? null) === 'database') {
                    $queueDb = DB::connection($mediaQueue['connection'] ?? null);
                    $table = $mediaQueue['table'] ?? 'jobs';
                    if ($queueDb->getSchemaBuilder()->hasTable($table)) {
                        $queue = $queueDb->table($table)->where('queue', 'gomovie-media');
                        $metrics['pending_media_jobs'] = $queue->count();
                        $oldest = $queue->whereNull('reserved_at')->where('available_at', '<=', now()->timestamp)
                            ->min('available_at');
                        $mediaQueueWait = $oldest === null ? null : max(0, now()->timestamp - (int) $oldest);
                        if ($mediaQueueWait > 900) $alerts[] = 'Media jobs have been waiting more than 15 minutes. Check the media worker.';
                    }
                }
                if (Schema::hasTable('failed_jobs')) {
                    $metrics['failed_jobs'] = DB::table('failed_jobs')->count();
                    if ($metrics['failed_jobs'] > 0) $alerts[] = 'Failed queue jobs require inspection.';
                }
                if (Schema::hasTable('stream_sessions')) {
                    $metrics['active_stream_sessions'] = DB::table('stream_sessions')
                        ->where('status','active')->where('expires_at','>',now())->count();
                }
                if (Schema::hasTable('media_ingests')) {
                    $metrics['quarantined_ingests'] = DB::table('media_ingests')
                        ->where('status','quarantined')->count();
                    $metrics['failed_media_ingests'] = DB::table('media_ingests')
                        ->where('status','failed')->count();

                    $recovery = app(PublicationRecovery::class);
                    $issues = $recovery->issues();
                    $metrics['media_needing_attention'] = $issues->count();
                    if ($metrics['media_needing_attention'] > 0) $alerts[] = 'Some media uploads need attention.';
                    $mediaIssues = $issues->orderByDesc('updated_at')->orderByDesc('id')->limit(10)->get()
                        ->map(function (object $ingest) use ($recovery): array {
                            $startedAt = $recovery->stageStartedAt($ingest);
                            return [
                                'id' => $ingest->id,
                                'stage' => PublicationRecovery::STAGES[$ingest->status] ?? 'Media processing',
                                'seconds_in_stage' => $startedAt ? max(0, now()->timestamp - $startedAt->timestamp) : null,
                                'issue' => $this->mediaIssue($ingest, $recovery),
                            ];
                        })->all();
                }
                if (Schema::hasTable('queue_probe_runs')) {
                    foreach (array_keys($probes) as $queue) {
                        $last = DB::table('queue_probe_runs')->where('queue_name',$queue)
                            ->whereNotNull('processed_at')->orderByDesc('processed_at')->first();
                        $probes[$queue] = $last ? [
                            'last_processed_at'=>$last->processed_at,
                            'seconds_ago'=>max(0,(int) \Illuminate\Support\Carbon::parse($last->processed_at)->diffInSeconds(now())),
                        ] : null;
                    }
                    if (SiteSettings::get('queue_probes_enabled') === true) {
                        foreach ($probes as $queue => $probe) {
                            if (!$probe || $probe['seconds_ago'] > 900) {
                                $alerts[] = 'Queue processing probe is stale: '.$queue.'.';
                            }
                        }
                    }
                }
            } catch (Throwable $error) {
                report($error);
                $checks['schema'] = 'unavailable';
                $alerts[] = 'Application metrics could not be collected.';
            }
        } else {
            $checks['schema'] = 'unknown';
        }

        $private = Storage::disk('local')->path('');
        $free = is_dir($private) ? disk_free_space($private) : false;
        $diskFree = $free === false ? null : $free;
        $diskTotal = is_dir($private) ? (disk_total_space($private) ?: null) : null;
        $checks['private_storage'] = is_dir($private) && is_writable($private) ? 'healthy' : 'unavailable';
        if ($checks['private_storage'] !== 'healthy') $alerts[] = 'Private media storage is not writable.';
        if ($diskFree !== null && $diskTotal !== null && ($diskFree < 2147483648 || $diskFree / $diskTotal < 0.1)) {
            $alerts[] = 'Private media storage is low. Free space or expand storage before uploading more videos.';
        }

        if (SiteSettings::get('transcoding_enabled') === true) {
            foreach (['ffmpeg' => 'FFmpeg', 'ffprobe' => 'FFprobe'] as $key => $label) {
                $checks[$key.'_binary'] = $this->binaryAvailable((string) config('gomovie.'.$key.'_binary'))
                    ? 'executable' : 'unavailable';
                if ($checks[$key.'_binary'] === 'unavailable') $alerts[] = $label.' executable is unavailable.';
            }
        }
        if (SiteSettings::get('automatic_publication_enabled') === true && SiteSettings::get('public_hls_enabled') === true) {
            $scanner = (string) config('gomovie.malware_scanner_binary');
            $checks['malware_scanner_binary'] = basename($scanner) === 'clamscan' && $this->binaryAvailable($scanner)
                ? 'executable' : 'unavailable';
            if ($checks['malware_scanner_binary'] === 'unavailable') $alerts[] = 'The ClamAV scanner executable is unavailable.';
        }

        if (SiteSettings::get('edge_enabled') === true) {
            try {
                // Read-only probe. No Redis check when private streaming is disabled.
                Cache::store('redis')->get('gomovie:ops:readiness');
                $checks['redis_lock_backend'] = 'healthy';
            } catch (Throwable $error) {
                report($error);
                $checks['redis_lock_backend'] = 'unavailable';
                $alerts[] = 'Redis playback coordination is unavailable.';
            }
        } else {
            $checks['redis_lock_backend'] = 'not_required';
        }

        $checks['smtp'] = in_array(config('mail.default'), ['log','array'],true)
            ? 'not_verified' : 'configured_not_tested';
        if (app()->environment('production') && $checks['smtp'] === 'not_verified') {
            $alerts[] = 'Production SMTP delivery has not been configured.';
        }
        if (app()->environment('production') && config('app.debug')) {
            $alerts[] = 'Debug mode is enabled in production.';
        }

        return [
            'checked_at'=>now()->toIso8601String(),
            'status'=>count($alerts) === 0 ? 'operational' : 'attention_required',
            'checks'=>$checks,
            'metrics'=>$metrics,
            'queue_probes'=>$probes,
            'oldest_media_wait_seconds'=>$mediaQueueWait,
            'private_disk_free_bytes'=>$diskFree,
            'media_issues'=>$mediaIssues,
            'feature_flags'=>[
                'private_edge_enabled'=>SiteSettings::get('edge_enabled') === true,
                'private_media_staging_enabled'=>SiteSettings::get('media_staging_enabled') === true,
                'transcoding_enabled'=>SiteSettings::get('transcoding_enabled') === true,
                'billing_enabled'=>config('gomovie.billing_enabled') === true,
                'queue_probes_enabled'=>SiteSettings::get('queue_probes_enabled') === true,
            ],
            'alerts'=>$alerts,
            'production_release_accepted'=>false,
        ];
    }

    private function binaryAvailable(string $path): bool
    {
        return str_starts_with($path, '/') && is_file($path) && is_executable($path);
    }

    private function mediaIssue(object $ingest, PublicationRecovery $recovery): string
    {
        if ($recovery->retryPending($ingest)) return 'Retry queued or running';

        return match ($ingest->pipeline_error) {
            'publication_queue_unavailable' => 'Queue unavailable',
            'active_free_distribution_rights_required', 'documented_distribution_rights_required' => 'Distribution rights need review',
            'malware_detected' => 'Security scan rejected this upload',
            'encoding_failed' => 'Encoding failed',
            'automatic_publication_failed' => 'Automatic processing failed',
            default => $recovery->stalled($ingest) ? 'Processing stalled' : 'Review upload',
        };
    }

}
