<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Operations\HostAcceptanceRegistry;
use Illuminate\Console\Command;

final class SubmitHostEvidence extends Command
{
    protected $signature = 'gomovie:acceptance-submit
        {target : aapanel or cpanel}
        {control : Acceptance control ID from docs}
        {--actor= : Existing verified administrator email}
        {--reference= : External evidence ticket identifier (no secrets)}';
    protected $description = 'Record a real-host evidence reference for independent review; no automatic release approval';

    public function handle(HostAcceptanceRegistry $registry): int
    {
        $actor = User::query()->where('email',(string)$this->option('actor'))->first();
        if (!$actor) {
            $this->error('Administrator not found.');
            return self::FAILURE;
        }
        if (!$this->confirm('Submit this reference for independent host review? No release will be enabled.', false)) {
            return self::FAILURE;
        }
        $registry->submit((string)$this->argument('target'),(string)$this->argument('control'),
            $actor,(string)$this->option('reference'));
        $this->info('Evidence reference submitted; independent super-admin review is pending.');
        return self::SUCCESS;
    }
}
