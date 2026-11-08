<?php

namespace Tests\Feature;

use App\Services\Installation\InstallReadiness;
use App\Services\Recovery\RecoveryBundleVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class InstallationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_schema_cannot_be_mistaken_for_a_fresh_install(): void
    {
        $report = app(InstallReadiness::class)->inspect('install');
        self::assertFalse($report['checks']['database_state_compatible']);
        self::assertFalse($report['ready_for_review']);
        self::assertFalse($report['production_release_accepted']);
    }

    public function test_upgrade_requires_existing_schema_and_integrity_checked_recovery_bundle(): void
    {
        $report = app(InstallReadiness::class)->inspect('upgrade');
        self::assertTrue($report['checks']['database_state_compatible']);
        self::assertFalse($report['checks']['recovery_bundle_integrity']);
        self::assertFalse($report['ready_for_review']);
        self::assertSame(1, Artisan::call('gomovie:install-preflight', [
            '--mode' => 'upgrade', '--json' => true,
        ]));
        self::assertStringContainsString('"independent_host_acceptance":false', Artisan::output());
        self::assertStringNotContainsString((string) config('app.key'), Artisan::output());
    }

    public function test_recovery_bundle_check_detects_tampering_and_unsafe_files(): void
    {
        $dir = sys_get_temp_dir().'/gomovie-recovery-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $names = [
            'database' => 'database.sql.gz',
            'private_media' => 'media.tar.gz',
            'configuration' => 'config.age',
        ];
        try {
            $artifacts = [];
            foreach ($names as $role => $filename) {
                $bytes = 'SYNTHETIC NONPRODUCTION '. $role;
                file_put_contents($dir.'/'.$filename, $bytes);
                $artifacts[] = [
                    'role' => $role, 'filename' => $filename,
                    'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                ];
            }
            file_put_contents($dir.'/manifest.json', json_encode([
                'format' => 'gomovie-recovery-v1',
                'revision' => str_repeat('a', 40),
                'artifacts' => $artifacts,
            ], JSON_THROW_ON_ERROR));

            $verifier = app(RecoveryBundleVerifier::class);
            self::assertTrue($verifier->verify($dir)['integrity_verified']);
            self::assertSame(0, Artisan::call('gomovie:recovery-check', [
                'directory' => $dir, '--json' => true,
            ]));
            self::assertStringContainsString('"restoration_verified":false', Artisan::output());

            file_put_contents($dir.'/media.tar.gz', 'CORRUPTED');
            self::assertFalse($verifier->verify($dir)['integrity_verified']);

            $artifacts[1]['filename'] = '../outside.tar.gz';
            file_put_contents($dir.'/manifest.json', json_encode([
                'format' => 'gomovie-recovery-v1',
                'revision' => str_repeat('a', 40),
                'artifacts' => $artifacts,
            ], JSON_THROW_ON_ERROR));
            self::assertFalse($verifier->verify($dir)['integrity_verified']);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) unlink($file);
            rmdir($dir);
        }
    }
}
