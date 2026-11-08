<?php

namespace Tests\Unit;

require_once __DIR__.'/../../support/installer/InstallerEngine.php';

use Gomovie\Installer\InstallerEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallerEngineTest extends TestCase
{
    public function test_url_is_auto_detected_and_non_https_remote_sites_are_rejected(): void
    {
        $url = InstallerEngine::detectedUrl([
            'HTTP_HOST'=>'Gomovie.Example:443','HTTPS'=>'on','SERVER_PORT'=>'443',
        ]);
        self::assertSame('https://gomovie.example:443',$url);
        self::assertSame('https://gomovie.example',
            InstallerEngine::validateUrl('https://gomovie.example',$url));
        $this->expectException(InvalidArgumentException::class);
        InstallerEngine::validateUrl('http://gomovie.example',$url);
    }

    public function test_other_hosts_and_urls_with_embedded_credentials_are_rejected(): void
    {
        foreach (['https://attacker.example','https://user:pass@gomovie.example',
                  'https://gomovie.example/admin','https://gomovie.example/?token=a'] as $input) {
            try {
                InstallerEngine::validateUrl($input,'https://gomovie.example');
                self::fail('Unsafe installer URL was accepted: '.$input);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_password_validation_database_constraints_and_purchase_code(): void
    {
        self::assertSame('admin@example.test',
            InstallerEngine::validateAdministrator([
                'name'=>'Admin Operator','email'=>'ADMIN@example.test',
                'password'=>'Strong-Install-123','password_confirmation'=>'Strong-Install-123',
            ])['email']);
        self::assertSame('mysql', InstallerEngine::normalizeDatabase([
            'driver'=>'mysql','host'=>'localhost','port'=>'3306',
            'database'=>'gomovie','username'=>'gomovie_user','password'=>'Complex$123',
        ])['driver']);
        InstallerEngine::validatePurchaseCode('MY-PURCHASE-CODE-01');
        try {
            InstallerEngine::validatePurchaseCode("bad\nCODE");
            self::fail('Injected Purchase Code was accepted.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(InvalidArgumentException::class);
        InstallerEngine::normalizeDatabase([
            'driver'=>'mysql','host'=>'localhost;socket=/tmp/fake','port'=>'3306',
            'database'=>'gomovie','username'=>'user','password'=>'pass',
        ]);
    }

    public function test_environment_contains_unique_key_and_disabled_flags_without_raw_password(): void
    {
        $db = InstallerEngine::normalizeDatabase([
            'driver'=>'mysql','host'=>'localhost','port'=>'3306',
            'database'=>'gomovie','username'=>'gomovie','password'=>'Complex"$Password',
        ]);
        $one = InstallerEngine::environment('https://gomovie.example',$db);
        $two = InstallerEngine::environment('https://gomovie.example',$db);
        self::assertNotSame($one,$two);
        self::assertStringContainsString('APP_DEBUG="false"',$one);
        self::assertStringContainsString('GOMOVIE_EDGE_ENABLED="false"',$one);
        self::assertStringContainsString('GOMOVIE_MEDIA_STAGING_ENABLED="false"',$one);
        self::assertStringContainsString('DB_PASSWORD="Complex\\"\\$Password"',$one);
        self::assertStringNotContainsString('Complex"$Password',$one);
    }

    public function test_private_files_cannot_be_overwritten(): void
    {
        $path = sys_get_temp_dir().'/gomovie-installer-file-'.bin2hex(random_bytes(8));
        try {
            InstallerEngine::createPrivateFile($path,"private-config\n");
            self::assertSame("private-config\n",file_get_contents($path));
            try {
                InstallerEngine::createPrivateFile($path,'replace');
                self::fail('Existing installer file overwritten.');
            } catch (RuntimeException) {
                self::assertSame("private-config\n",file_get_contents($path));
            }
        } finally {
            @unlink($path);
        }
    }
}
