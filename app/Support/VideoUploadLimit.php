<?php

namespace App\Support;

final class VideoUploadLimit
{
    public const DEFAULT_MB = 128;

    public const MAX_MB = 2048;

    public static function megabytes(): int
    {
        return (int) (SiteSettings::get('video_upload_max_mb') ?? self::DEFAULT_MB);
    }

    public static function bytes(): int
    {
        return self::megabytes() * 1048576;
    }

    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:mp4,mov,mkv,webm', 'max:'.(self::megabytes() * 1024)];
    }

    public static function effectiveBytes(): int
    {
        $limit = self::bytes();
        $fileMax = ini_parse_quantity((string) ini_get('upload_max_filesize'));
        $postMax = ini_parse_quantity((string) ini_get('post_max_size'));
        if ($fileMax > 0) {
            $limit = min($limit, $fileMax);
        }
        if ($postMax > 0) {
            $limit = min($limit, max(1, $postMax - 65536));
        }

        return $limit;
    }
}
