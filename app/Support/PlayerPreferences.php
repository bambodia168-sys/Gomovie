<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlayerPreferences
{
    public const DEFAULTS = [
        'accent' => 'crimson',
        'density' => 'comfortable',
        'seek_seconds' => 10,
        'auto_hide_seconds' => 1,
        'show_volume' => true,
        'show_speed' => true,
        'show_captions' => true,
        'show_pip' => true,
        'show_fullscreen' => true,
    ];

    public const ACCENTS = [
        'crimson' => '#f33060',
        'violet' => '#a78bfa',
        'cyan' => '#22d3ee',
        'gold' => '#f5c15d',
    ];

    public static function current(): array
    {
        // The first visit remains safe before an independent installer
        // completes the additive migration.
        if (!Schema::hasTable('player_preferences')) {
            return self::DEFAULTS;
        }

        $row = DB::table('player_preferences')->where('id', 1)->first();
        if (!$row) {
            return self::DEFAULTS;
        }

        $data = (array) $row;
        $out = self::DEFAULTS;
        foreach (array_keys($out) as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }
        // Defensive value normalization: never inject an arbitrary style.
        $out['accent'] = array_key_exists($out['accent'], self::ACCENTS)
            ? $out['accent'] : 'crimson';
        $out['density'] = in_array($out['density'], ['comfortable', 'compact'], true)
            ? $out['density'] : 'comfortable';
        $out['seek_seconds'] = in_array((int) $out['seek_seconds'], [5, 10, 15, 30], true)
            ? (int) $out['seek_seconds'] : 10;
        $out['auto_hide_seconds'] = in_array((int) $out['auto_hide_seconds'], [0, 1, 2], true)
            ? (int) $out['auto_hide_seconds'] : 1;
        foreach (['show_volume', 'show_speed', 'show_captions', 'show_pip', 'show_fullscreen'] as $key) {
            $out[$key] = (bool) $out[$key];
        }
        return $out;
    }

    public static function accentColor(array $preferences): string
    {
        return self::ACCENTS[$preferences['accent']] ?? self::ACCENTS['crimson'];
    }
}
