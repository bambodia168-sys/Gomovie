<?php

namespace App\Console\Commands;

use App\Services\Recovery\RecoveryBundleVerifier;
use Illuminate\Console\Command;

final class RecoveryCheck extends Command
{
    protected $signature = 'gomovie:recovery-check
        {directory : Offline bundle containing manifest.json and the required archives}
        {--json : Print safe machine-readable results}';
    protected $description = 'Verify offline recovery artifacts without restoring or altering any live data';

    public function handle(RecoveryBundleVerifier $verifier): int
    {
        $report = $verifier->verify((string) $this->argument('directory'));
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        } else {
            foreach ($report['checks'] as $name => $status) {
                $this->line(($status ? '[PASS] ' : '[BLOCKED] ').str_replace('_', ' ', $name));
            }
            $this->warn('Artifact integrity alone does not verify off-site availability or restoration.');
        }
        return $report['integrity_verified'] ? self::SUCCESS : self::FAILURE;
    }
}
