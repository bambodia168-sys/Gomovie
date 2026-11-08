<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Catalog artwork is presentation-only. It may be a TMDB poster path
 * imported from metadata or an approved admin-uploaded public artwork key.
 * Never accept arbitrary remote URLs from catalog fields.
 */
final class CatalogArtwork
{
    public static function url(?string $path, string $size = 'w500'): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (in_array($size, ['w342', 'w500', 'w780', 'original'], true)
            && preg_match('#\A/[A-Za-z0-9_/-]+\.(?:jpg|jpeg|png|webp)\z#iD', $path)) {
            return 'https://image.tmdb.org/t/p/'.$size.$path;
        }

        if (preg_match('#\Aartwork/[A-Za-z0-9_/-]+\.(?:jpg|jpeg|png|webp)\z#iD', $path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }
}
