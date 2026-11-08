<?php

declare(strict_types=1);

namespace Gomovie\Installer;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Zero-framework installer validation used before Laravel or the database
 * exists. The public installer is the only caller that can write setup state.
 * Production supports MySQL 8 and PostgreSQL 16; no SQLite installer.
 */
final class InstallerEngine
{
    public static function requirements(string $root): array
    {
        $private = $root.'/storage/app/private';
        $sessions = $root.'/storage/framework';
        $views = $sessions.'/views';
        $logs = $root.'/storage/logs';

        return [
            'PHP 8.4 or newer' => PHP_VERSION_ID >= 80400,
            'PDO, OpenSSL, mbstring and fileinfo' =>
                extension_loaded('pdo') && extension_loaded('openssl')
                && extension_loaded('mbstring') && extension_loaded('fileinfo'),
            'MySQL or PostgreSQL PDO driver' =>
                extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql'),
            'Composer dependencies bundled' => is_file($root.'/vendor/autoload.php'),
            'Compiled frontend assets bundled' => is_file($root.'/public/build/manifest.json'),
            'Private application storage' => is_dir($private) && is_writable($private),
            'Private session and view storage' => is_dir($sessions) && is_writable($sessions)
                && is_dir($views) && is_writable($views),
            'Application logs writable' => is_dir($logs) && is_writable($logs),
            'Application directory can create .env' => !file_exists($root.'/.env')
                && !is_link($root.'/.env') && is_writable($root),
        ];
    }

    public static function detectedUrl(array $server): string
    {
        $host = trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        if (!preg_match('/\A[a-zA-Z0-9.-]+(?::[0-9]{1,5})?\z/D', $host)) {
            return '';
        }
        $secure = ($server['HTTPS'] ?? '') === 'on' || ($server['HTTPS'] ?? '') === '1'
            || (string) ($server['SERVER_PORT'] ?? '') === '443';
        return ($secure ? 'https://' : 'http://').strtolower($host);
    }

    public static function validateUrl(string $url, string $detectedUrl): string
    {
        $url = trim($url);
        if (strlen($url) > 253 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Enter a valid application URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['scheme'], ['https','http'], true)
            || !in_array($parts['path'] ?? '', ['', '/'], true)
            || !preg_match('/\A[a-zA-Z0-9.-]+\z/D', $parts['host'])) {
            throw new InvalidArgumentException('Application URL must be a domain root, without a path or credentials.');
        }
        $local = in_array(strtolower($parts['host']), ['localhost','127.0.0.1'], true);
        if ($parts['scheme'] !== 'https' && !$local) {
            throw new InvalidArgumentException('HTTPS is required for non-local installations.');
        }
        // The installer must not silently configure a different host than the
        // operator used to access it. Validate reverse-proxy TLS at host level.
        $detectedHost = parse_url($detectedUrl, PHP_URL_HOST);
        if (!$detectedHost || strtolower($parts['host']) !== strtolower((string) $detectedHost)) {
            throw new InvalidArgumentException('Application domain must match the current installer host.');
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).$port;
    }

    public static function validatePurchaseCode(string $code): void
    {
        if ($code !== '' && !preg_match('/\A[A-Za-z0-9-]{6,120}\z/D', $code)) {
            throw new InvalidArgumentException('Purchase Code format is invalid.');
        }
        // Intentionally does not attest licensing or submit a code to a server.
    }

    public static function normalizeDatabase(array $fields): array
    {
        $driver = (string) ($fields['driver'] ?? '');
        if (!in_array($driver, ['mysql','pgsql'], true)) {
            throw new InvalidArgumentException('Choose MySQL or PostgreSQL.');
        }
        $host = trim((string) ($fields['host'] ?? ''));
        $port = filter_var($fields['port'] ?? null, FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>65535]]);
        $name = trim((string) ($fields['database'] ?? ''));
        $username = trim((string) ($fields['username'] ?? ''));
        $password = (string) ($fields['password'] ?? '');
        if (!preg_match('/\A[a-zA-Z0-9.-]{1,253}\z/D', $host)
            || $port === false || !preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name)
            || !preg_match('/\A[A-Za-z0-9_.@-]{1,128}\z/D', $username)
            || strlen($password) > 256
            || str_contains($password, "\n") || str_contains($password, "\r")
            || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Invalid database host, port, name, username, or password.');
        }
        return compact('driver','host','port','name','username','password');
    }

    public static function connect(array $db): PDO
    {
        $driver = $db['driver'];
        $dsn = $driver === 'mysql'
            ? 'mysql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['name'].';charset=utf8mb4'
            : 'pgsql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['name'];
        return new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT=>5,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        ]);
    }

    public static function databaseEmpty(PDO $pdo, string $driver): bool
    {
        if ($driver === 'mysql') {
            $query = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()';
        } elseif ($driver === 'pgsql') {
            $query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'";
        } else {
            throw new InvalidArgumentException('Unsupported production database.');
        }
        return (int) $pdo->query($query)->fetchColumn() === 0;
    }

    public static function validateAdministrator(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || strlen($password) < 12 || strlen($password) > 128
            || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)
            || !hash_equals($password, (string) ($input['password_confirmation'] ?? ''))) {
            throw new InvalidArgumentException('Enter a valid administrator name, email, and confirmed strong password.');
        }
        return ['name'=>$name,'email'=>$email,'password'=>$password];
    }

    public static function environment(string $url, array $db): string
    {
        $values = [
            'APP_NAME'=>'Gomovie',
            'APP_ENV'=>'production',
            'APP_KEY'=>'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG'=>'false',
            'APP_URL'=>$url,
            'DB_CONNECTION'=>$db['driver'],
            'DB_HOST'=>$db['host'],
            'DB_PORT'=>(string) $db['port'],
            'DB_DATABASE'=>$db['name'],
            'DB_USERNAME'=>$db['username'],
            'DB_PASSWORD'=>$db['password'],
            'SESSION_DRIVER'=>'database',
            'CACHE_STORE'=>'database',
            'QUEUE_CONNECTION'=>'database',
            'FILESYSTEM_DISK'=>'local',
            'MAIL_MAILER'=>'log',
            'GOMOVIE_BILLING_ENABLED'=>'false',
            'GOMOVIE_EDGE_ENABLED'=>'false',
            'GOMOVIE_TRANSCODING_ENABLED'=>'false',
            'GOMOVIE_MEDIA_STAGING_ENABLED'=>'false',
            'GOMOVIE_QUEUE_PROBES_ENABLED'=>'false',
            'GOMOVIE_MEDIA_QUEUE_CONNECTION'=>'database_media',
        ];
        $lines = [];
        foreach ($values as $key=>$value) {
            if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
                throw new RuntimeException('Unsafe environment value.');
            }
            $escaped = str_replace(['\\','"','$'], ['\\\\','\\"','\\$'], $value);
            $lines[] = $key.'="'.$escaped.'"';
        }
        return implode("\n", $lines)."\n";
    }

    /**
     * Exclusive create; never overwrite a prior installation or env file.
     * A partial migration must be resolved by an operator, not auto-retried.
     */
    public static function createPrivateFile(string $path, string $content): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Unsafe installation file path.');
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Existing installation file must not be replaced.');
        }
        try {
            @chmod($path, 0600);
            $length = strlen($content);
            $written = 0;
            while ($written < $length) {
                $n = fwrite($handle, substr($content,$written));
                if ($n === false || $n === 0) {
                    throw new RuntimeException('Unable to persist private installation file.');
                }
                $written += $n;
            }
            fflush($handle);
        } finally {
            fclose($handle);
        }
    }
}
