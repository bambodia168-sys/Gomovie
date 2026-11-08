<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                    $metrics['pending_media_jobs'] = DB::table('jobs')->where('queue','gomovie-media')->count();
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
                    if (config('gomovie.queue_probes_enabled') === true) {
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

        $private = storage_path('app/private');
        $checks['private_storage'] = is_dir($private) && is_writable($private) ? 'healthy' : 'unavailable';
        if ($checks['private_storage'] !== 'healthy') $alerts[] = 'Private media storage is not writable.';

        if (config('gomovie.edge_enabled') === true) {
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
            'feature_flags'=>[
                'private_edge_enabled'=>config('gomovie.edge_enabled') === true,
                'private_media_staging_enabled'=>config('gomovie.media_staging_enabled') === true,
                'transcoding_enabled'=>config('gomovie.transcoding_enabled') === true,
                'billing_enabled'=>config('gomovie.billing_enabled') === true,
                'queue_probes_enabled'=>config('gomovie.queue_probes_enabled') === true,
            ],
            'alerts'=>$alerts,
            'production_release_accepted'=>false,
        ];
    }
}
