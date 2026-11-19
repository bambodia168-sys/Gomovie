<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SiteSettings
{
    public const SECRETS = ['tmdb_token', 'r2_key', 'r2_secret', 'media_signing_key', 'edge_shared_key'];

    public const R2_ENDPOINT = '#\Ahttps://[a-f0-9]{32}\.r2\.cloudflarestorage\.com/?\z#D';

    private const R2_DISK_KEYS = [
        'endpoint' => 'r2_endpoint',
        'bucket' => 'r2_bucket',
        'key' => 'r2_key',
        'secret' => 'r2_secret',
    ];

    private ?array $overrides = null;

    public static function get(string $key): mixed
    {
        return app(self::class)->value($key);
    }

    public static function brandAsset(string $key, string $default): string
    {
        $path = self::get($key);

        return $path ? asset('storage/'.$path) : asset($default);
    }

    private function value(string $key): mixed
    {
        $this->overrides ??= Schema::hasTable('site_settings')
            ? DB::table('site_settings')->pluck('value', 'key')->all()
            : [];

        if (array_key_exists($key, $this->overrides)) {
            $value = $this->overrides[$key];
            if (in_array($key, self::SECRETS, true)) {
                $value = Crypt::decryptString($value);
            }

            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }

        return match ($key) {
            'r2_key' => config('filesystems.disks.r2.key'),
            'r2_secret' => config('filesystems.disks.r2.secret'),
            'tmdb_enabled' => strlen(trim((string) $this->value('tmdb_token'))) >= 20,
            'site_name' => 'Go Movie',
            'footer_url' => 'https://tonsamith.com',
            default => config('gomovie.'.$key),
        };
    }

    public function save(array $changes): void
    {
        $rows = [];
        foreach ($changes as $key => $value) {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            $rows[] = [
                'key' => $key,
                'value' => in_array($key, self::SECRETS, true) ? Crypt::encryptString($encoded) : $encoded,
            ];
        }
        if ($rows !== []) {
            DB::table('site_settings')->upsert($rows, ['key'], ['value']);
        }
        $this->overrides = null;
    }

    public static function r2Disk(): Filesystem
    {
        return app(self::class)->configuredR2Disk();
    }

    private function configuredR2Disk(): Filesystem
    {
        $base = config('filesystems.disks.r2');
        $effective = $base;
        foreach (self::R2_DISK_KEYS as $option => $key) {
            $value = $this->value($key);
            if (array_key_exists($key, $this->overrides)) {
                $effective[$option] = $value;
            }
        }

        return $effective === $base ? Storage::disk('r2') : Storage::build($effective);
    }
}
