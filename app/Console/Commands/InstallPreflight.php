<?php

namespace App\Console\Commands;

use App\Services\Installation\InstallReadiness;
use Illuminate\Console\Command;

final class InstallPreflight extends Command
{
    protected $signature = 'gomovie:install-preflight
        {--mode=install : install on an unused database, or upgrade an existing schema}
        {--recovery-bundle= : Directory with a verified offline recovery manifest and archives}
        {--json : Print machine-readable, secret-free results}';
    protected $description = 'Read-only, fail-closed host and database installation/upgrade checks';

    public function handle(InstallReadiness $readiness): int
    {
        $mode = (string) $this->option('mode');
        if (!in_array($mode, ['install', 'upgrade'], true)) {
            $this->error('Mode must be install or upgrade.');
            return self::FAILURE;
        }

        $path = $this->option('recovery-bundle');
        $report = $readiness->inspect($mode, is_string($path) && $path !== '' ? $path : null);
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        } else {
            foreach ($report['checks'] as $name => $status) {
                $this->line(($status ? '[PASS] ' : '[BLOCKED] ').str_replace('_', ' ', $name));
            }
            $this->warn('Independent webserver, TLS, backup restoration and device acceptance remain required.');
        }

        return $report['ready_for_review'] ? self::SUCCESS : self::FAILURE;
    }
}
