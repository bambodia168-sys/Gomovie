<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * A harmless marker that proves a real asynchronous queue has executed a job.
 * This does not mark any video or financial workflow as production-ready.
 */
final class ProcessQueueProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(public readonly string $probeId, public readonly string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function handle(): void
    {
        DB::table('queue_probe_runs')->where('id', $this->probeId)
            ->where('queue_name', $this->queueName)
            ->whereNull('processed_at')
            ->where('expires_at', '>', now())
            ->update(['processed_at' => now(), 'updated_at' => now()]);
    }
}
