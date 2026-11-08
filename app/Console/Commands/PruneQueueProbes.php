<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneQueueProbes extends Command
{
    protected $signature = 'gomovie:prune-queue-probes {--days=30 : Retain the most recent days of probe history}';
    protected $description = 'Prune expired transient queue probes, without touching audit events or jobs';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>7,'max_range'=>365]]);
        if ($days === false) {
            $this->error('Retention must be between 7 and 365 days.');
            return self::FAILURE;
        }
        $count = DB::table('queue_probe_runs')
            ->where('expires_at','<',now()->subDays($days))
            ->delete();
        $this->line('Pruned '.$count.' expired operational probes.');
        return self::SUCCESS;
    }
}
