<?php

namespace App\Services\Installation;

use App\Services\Recovery\RecoveryBundleVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only, sanitized installation/upgrade preflight. It never creates
 * admin users, writes .env, applies migrations, or changes runtime flags.
 */
final class InstallReadiness
{
    public function inspect(string $mode, ?string $recoveryDirectory = null): array
    {
        if (!in_array($mode, ['install', 'upgrade'], true)) {
            throw new \InvalidArgumentException('Unsupported installation mode.');
        }

        $production = config('app.env') === 'production';
        $databaseDriver = config('database.default');
        $extensions = ['pdo', 'mbstring', 'openssl', 'fileinfo'];
        $extensions[] = match ($databaseDriver) {
            'mysql', 'mariadb' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            default => '__unsupported_database_driver__',
        };
        $key = config('app.key');
        $decodedKey = is_string($key) && str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true) : $key;
        $envPath = base_path('.env');
        $envMode = is_file($envPath) && !is_link($envPath) ? fileperms($envPath) : false;
        $private = storage_path('app/private');
        $checks = [
            'supported_php' => PHP_VERSION_ID >= 80400,
            'required_extensions' => count(array_filter($extensions, fn ($extension) => !extension_loaded($extension))) === 0,
            'app_key_present' => is_string($decodedKey) && strlen($decodedKey) === 32,
            'production_debug_disabled' => !$production || config('app.debug') === false,
            'production_url_https' => !$production || str_starts_with((string) config('app.url'), 'https://'),
            'production_database_supported' => !$production || in_array($databaseDriver, ['mysql','pgsql'], true),
            'environment_file_private' => !$production || (is_int($envMode) && ($envMode & 0007) === 0),
            'compiled_frontend_present' => is_file(public_path('build/manifest.json')),
            'application_storage_writable' => is_dir(storage_path('framework/views'))
                && is_writable(storage_path('framework/views'))
                && is_dir(storage_path('logs')) && is_writable(storage_path('logs')),
            'private_media_storage_writable' => is_dir($private) && is_writable($private),
            'dangerous_features_disabled' => config('gomovie.billing_enabled') !== true
                && config('gomovie.edge_enabled') !== true
                && config('gomovie.transcoding_enabled') !== true
                && config('gomovie.media_staging_enabled') !== true,
            'database_reachable' => false,
            'database_state_compatible' => false,
        ];

        try {
            DB::select('SELECT 1');
            $checks['database_reachable'] = true;
            $hasMigrations = Schema::hasTable('migrations');
            $hasUsers = Schema::hasTable('users');
            $checks['database_state_compatible'] = $mode === 'install'
                ? (!$hasMigrations && !$hasUsers && count(Schema::getTableListing()) === 0)
                : ($hasMigrations && $hasUsers && Schema::hasTable('media_ingests')
                    && Schema::hasTable('admin_audit_events'));
        } catch (Throwable) {
            // Never return driver messages or connection credentials.
        }

        $recovery = null;
        if ($mode === 'upgrade') {
            $recovery = $recoveryDirectory !== null
                ? app(RecoveryBundleVerifier::class)->verify($recoveryDirectory) : null;
            // An upgrade must be able to point to independently generated
            // verified artifacts; a backup restore drill is still mandatory.
            $checks['recovery_bundle_integrity'] = $recovery !== null && $recovery['integrity_verified'];
        }

        return [
            'mode' => $mode,
            'environment' => $production ? 'production' : 'nonproduction',
            'ready_for_review' => !in_array(false, $checks, true),
            'checks' => $checks,
            'recovery' => $recovery,
            'webserver_tls_and_docroot_verified' => false,
            'independent_host_acceptance' => false,
            'production_release_accepted' => false,
        ];
    }
}
