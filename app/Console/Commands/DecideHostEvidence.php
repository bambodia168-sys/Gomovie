<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Operations\HostAcceptanceRegistry;
use Illuminate\Console\Command;

final class DecideHostEvidence extends Command
{
    protected $signature = 'gomovie:acceptance-review
        {target : aapanel or cpanel}
        {control : Acceptance control ID from docs}
        {--reviewer= : Verified super-admin email distinct from submitter}
        {--decision= : accept or reject evidence after independent real-world verification}';
    protected $description = 'Audit a separated reviewer decision on submitted host acceptance evidence';

    public function handle(HostAcceptanceRegistry $registry): int
    {
        $reviewer = User::query()->where('email',(string)$this->option('reviewer'))->first();
        if (!$reviewer) {
            $this->error('Reviewer not found.');
            return self::FAILURE;
        }
        if (!$this->confirm('Record reviewer decision? A reference is not proof of actual hosting acceptance.', false)) {
            return self::FAILURE;
        }
        $registry->decide((string)$this->argument('target'),(string)$this->argument('control'),
            $reviewer,(string)$this->option('decision'));
        $this->info('Decision recorded. Live production release status is not changed.');
        return self::SUCCESS;
    }
}
