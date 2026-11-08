<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalSnapshot;
use Illuminate\Console\Command;

final class OperationsCheck extends Command
{
    protected $signature = 'gomovie:ops-check {--json : Emit a non-sensitive JSON snapshot}';
    protected $description = 'Read-only database, queue, storage and safe feature-flag diagnostics';

    public function handle(OperationalSnapshot $snapshot): int
    {
        $result = $snapshot->inspect();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->info('Gomovie operational checks — '.$result['status']);
            foreach ($result['checks'] as $key=>$status) {
                $this->line(str_replace('_',' ',$key).': '.$status);
            }
            foreach ($result['alerts'] as $alert) {
                $this->warn($alert);
            }
            $this->line('Production acceptance: not established by this diagnostic.');
        }

        return $result['status'] === 'operational' ? self::SUCCESS : self::FAILURE;
    }
}
