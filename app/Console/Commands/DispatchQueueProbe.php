<?php

namespace App\Console\Commands;

use App\Jobs\ProcessQueueProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DispatchQueueProbe extends Command
{
    protected $signature = 'gomovie:queue-probe {queue=default : default or gomovie-media}';
    protected $description = 'Dispatch a safe queue execution probe (never runs media processing)';

    public function handle(): int
    {
        $queue = (string) $this->argument('queue');
        if (!in_array($queue, ['default', 'gomovie-media'], true)) {
            $this->error('Only default and gomovie-media queue probes are supported.');
            return self::FAILURE;
        }

        $connection = $queue === 'gomovie-media'
            ? config('gomovie.media_queue_connection')
            : config('queue.default');
        if (!in_array($connection, ['database','redis','database_media','redis_media'],true)
            || ($queue === 'gomovie-media' && !in_array($connection,['database_media','redis_media'],true))) {
            $this->error('Use a real asynchronous and isolated queue connection for this probe.');
            return self::FAILURE;
        }

        $probeId = (string) Str::uuid();

        try {
            DB::table('queue_probe_runs')->insert([
                'id'=>$probeId, 'queue_name'=>$queue,
                'dispatched_at'=>now(), 'expires_at'=>now()->addMinutes(10),
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            ProcessQueueProbe::dispatch($probeId, $queue);
        } catch (Throwable $error) {
            report($error);
            $this->error('The probe could not be dispatched; check protected application logs.');
            return self::FAILURE;
        }

        $this->line('Queued health probe: '.$queue.' ('.$probeId.')');
        $this->line('A worker must process this job before the monitor can report recent activity.');
        return self::SUCCESS;
    }
}
