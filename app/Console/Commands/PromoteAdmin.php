<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdmin extends Command
{
    protected $signature = 'gomovie:grant-admin {email : Existing verified account}';
    protected $description = 'Grant the super-admin role to a verified account with explicit server operator approval';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        if (!$user || !$user->hasVerifiedEmail() || !$user->is_active) {
            $this->error('A verified, active existing account is required.');
            return self::FAILURE;
        }

        if (!$this->confirm('Grant super_admin to '.$user->email.'?', false)) {
            $this->warn('No account changes made.');
            return self::FAILURE;
        }

        $user->forceFill(['role' => 'super_admin'])->save();
        \Illuminate\Support\Facades\DB::table('admin_audit_events')->insert([
            'actor_id' => $user->id,
            'action' => 'server.grant_super_admin',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'details' => json_encode(['source' => 'interactive-cli']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info('Super admin role granted.');
        return self::SUCCESS;
    }
}
