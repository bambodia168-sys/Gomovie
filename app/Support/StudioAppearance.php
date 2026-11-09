<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StudioAppearance
{
    public const PAGES = [
        'home' => ['label'=>'Homepage','route'=>'home','eyebrow'=>'Discover Gomovie','heading'=>'A world of stories. Made for you.','description'=>'Discover films and series on Gomovie.'],
        'browse' => ['label'=>'Browse catalog','route'=>'browse','eyebrow'=>'Discover Gomovie','heading'=>'Movies & Series','description'=>'Explore published catalog titles. Playback depends on approved distribution rights and availability.'],
        'about' => ['label'=>'About','route'=>'about','eyebrow'=>'About the platform','heading'=>'Stories worth discovering.','description'=>'Gomovie is a developing catalog and streaming platform for movies and TV series. Our public pages show published titles; playback remains restricted to content with approved rights, secure delivery and verified access.'],
        'watch' => ['label'=>'Watch title template','route'=>null,'eyebrow'=>null,'heading'=>null,'description'=>null],
        'login' => ['label'=>'Sign in','route'=>'login','eyebrow'=>null,'heading'=>'Welcome back','description'=>'Sign in to manage your Gomovie account and continue exploring.'],
        'register' => ['label'=>'Registration','route'=>'register','eyebrow'=>null,'heading'=>'Create an account','description'=>''],
        'forgot-password' => ['label'=>'Password recovery','route'=>'password.request','eyebrow'=>null,'heading'=>'Reset password','description'=>''],
    ];

    public static function page(string $slug): ?object
    {
        // Read the authoritative row for each request. In long-lived workers,
        // PHP static properties could otherwise show stale edits or deleted pages.
        return Schema::hasTable('studio_pages')
            ? DB::table('studio_pages')->where('slug', $slug)->first() : null;
    }

    public static function text(string $page, string $field, ?string $fallback = null): string
    {
        if (!in_array($field, ['eyebrow','heading','description','seo_title','seo_description','body'], true)) {
            return $fallback ?? '';
        }
        $row = self::page($page);
        if ($row && (!$row->is_custom || $row->is_published)) {
            $value = trim((string) ($row->$field ?? ''));
            if ($value !== '') return $value;
        }
        return $fallback ?? (string) (self::PAGES[$page][$field] ?? '');
    }

    public static function typography(): object
    {
        return (Schema::hasTable('studio_typography')
            ? DB::table('studio_typography')->where('id', 1)->first() : null)
            ?? (object) ['family'=>'Instrument Sans','base_size'=>16,'heading_scale'=>100,'weight'=>600,'custom_font_key'=>null];
    }

    public static function css(): string
    {
        $data = self::typography();
        // Never change the existing public visual design until a typography
        // override exists. Query fresh so updates become visible immediately.
        if (!Schema::hasTable('studio_typography')
            || !DB::table('studio_typography')->where('id',1)->exists()) return '';
        $families = [
            'Instrument Sans'=>'"Instrument Sans",sans-serif',
            'Inter'=>'Inter,Arial,sans-serif', 'Arial'=>'Arial,sans-serif',
            'Georgia'=>'Georgia,serif', 'System UI'=>'system-ui,sans-serif',
            'Custom'=>'"Gomovie Custom",sans-serif',
        ];
        $font = $families[$data->family] ?? $families['Instrument Sans'];
        $size = max(14, min(20, (int) $data->base_size));
        $scale = max(90, min(120, (int) $data->heading_scale));
        $weight = in_array((int) $data->weight, [400,500,600,700,800,900], true)
            ? (int) $data->weight : 600;
        $face = '';
        if ($data->family === 'Custom' && $data->custom_font_key
            && preg_match('/\Afonts\/[a-f0-9]{64}\.woff2\z/D', $data->custom_font_key)) {
            $face = '@font-face{font-family:"Gomovie Custom";src:url("'
                .route('appearance.font').'") format("woff2");font-display:swap;}';
        }
        $scaleCss = $scale === 100 ? '' :
            '.gm-site h1,.gm-auth-body h1{font-size:clamp(25px,'
            .'calc(3.2vw * '.($scale / 100).'),'
            .'calc(64px * '.($scale / 100).'))}.gm-site h2,.gm-auth-body h2{'
            .'font-size:clamp(20px,calc(2.2vw * '.($scale / 100).'),'
            .'calc(42px * '.($scale / 100).'))}';
        return $face.'.gm-site,.gm-auth-body{--gm-custom-font:'.$font
            .';--gm-custom-base:'.$size.'px;--gm-custom-heading-scale:'.($scale / 100)
            .';--gm-custom-heading-weight:'.$weight.';}'
            .'.gm-site,.gm-auth-body{font-family:var(--gm-custom-font);font-size:var(--gm-custom-base);}'
            .'.gm-site h1,.gm-site h2,.gm-auth-body h1,.gm-auth-body h2{font-weight:var(--gm-custom-heading-weight);}'
            .$scaleCss;
    }
}
