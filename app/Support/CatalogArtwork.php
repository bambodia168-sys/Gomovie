<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Catalog artwork may be a TMDB path, an uploaded public artwork key, or a
 * public HTTPS image URL. External artwork is loaded directly by the browser.
 */
final class CatalogArtwork
{
    public static function url(?string $path, string $size = 'w500'): ?string
    {
        $path = trim((string) $path);
        if (self::isHttpsImageUrl($path)) {
            return $path;
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (in_array($size, ['w342', 'w500', 'w780', 'original'], true)
            && preg_match('#\A/[A-Za-z0-9_/-]+\.(?:jpg|jpeg|png|webp)\z#iD', $path)) {
            return 'https://image.tmdb.org/t/p/'.$size.$path;
        }

        if (self::isStorageKey($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    public static function formReference(?string $path): string
    {
        $path = (string) $path;

        return self::isStorageKey($path) ? Storage::disk('public')->url($path) : $path;
    }

    public static function normalizeReference(string $reference): string
    {
        $prefix = rtrim(Storage::disk('public')->url('artwork'), '/').'/';
        if (str_starts_with($reference, $prefix)) {
            $key = 'artwork/'.substr($reference, strlen($prefix));
            if (self::isStorageKey($key)) {
                return $key;
            }
        }

        return $reference;
    }

    private static function isStorageKey(string $path): bool
    {
        return (bool) preg_match('#\Aartwork/[A-Za-z0-9_/-]+\.(?:jpg|jpeg|png|webp)\z#iD', $path);
    }

    private static function isHttpsImageUrl(string $url): bool
    {
        if (!str_starts_with(strtolower($url), 'https://')
            || str_contains($url, '\\')
            || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE);
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && preg_match('/\.[a-z][a-z0-9-]*$/D', $host)
            && !preg_match('/\.(?:localhost|local|internal|home\.arpa)$/D', $host);
    }
}
