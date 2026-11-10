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
        'watch' => ['label'=>'Watch title template','route'=>null,'eyebrow'=>null,'heading'=>'Story details','description'=>null],
        'login' => ['label'=>'Sign in','route'=>'login','eyebrow'=>null,'heading'=>'Welcome back','description'=>'Sign in to manage your account.'],
        'register' => ['label'=>'Registration','route'=>'register','eyebrow'=>null,'heading'=>'Create Account','description'=>'Build your own movies and series watchlist.'],
        'forgot-password' => ['label'=>'Password recovery','route'=>'password.request','eyebrow'=>null,'heading'=>'Forgot password?','description'=>"Enter your account email. We'll send reset instructions if the account exists."],
        'reset-password' => ['label'=>'Reset password form','route'=>null,'eyebrow'=>null,'heading'=>'Choose a new password','description'=>'Confirm your account email and choose a strong new password.'],
        'email-verification' => ['label'=>'Email verification','route'=>null,'eyebrow'=>null,'heading'=>'Check your inbox','description'=>'A secure verification link has been sent to your email address. Verify it before accessing your Gomovie account and personal library.'],
    ];

    /**
     * Stable, text-only frontend slots. Editing these never modifies route
     * destinations, markup, pricing, access control, or layout structure.
     */
    public const SLOTS = [
        'home-promo-description' => ['page'=>'Homepage','label'=>'Promo strip · subtitle','default'=>'Discover films · Explore series · Build your watchlist'],
        'home-top-picks' => ['page'=>'Homepage','label'=>'Top Picks section heading','default'=>'Top Picks for You'],
        'home-new-releases' => ['page'=>'Homepage','label'=>'New Releases section heading','default'=>'New Releases'],
        'home-trending' => ['page'=>'Homepage','label'=>'Trending section heading','default'=>'Trending Now'],
        'home-recently-added' => ['page'=>'Homepage','label'=>'Recently Added section heading','default'=>'Recently Added'],
        'about-explore-heading' => ['page'=>'About','label'=>'Explore card · heading','default'=>'Explore'],
        'about-explore-description' => ['page'=>'About','label'=>'Explore card · text','default'=>'Find movies, TV series and episode information in one catalog.'],
        'about-library-heading' => ['page'=>'About','label'=>'Library card · heading','default'=>'Personal library'],
        'about-library-description' => ['page'=>'About','label'=>'Library card · text','default'=>'Registered, verified viewers can save titles to their private lists.'],
        'about-delivery-heading' => ['page'=>'About','label'=>'Delivery card · heading','default'=>'Responsible delivery'],
        'about-delivery-description' => ['page'=>'About','label'=>'Delivery card · text','default'=>'Billing, private streaming and media processing are enabled only after independent acceptance.'],
        'browse-movies-heading' => ['page'=>'Browse','label'=>'Movies group heading','default'=>'Movies'],
        'browse-series-heading' => ['page'=>'Browse','label'=>'Series group heading','default'=>'TV Series'],
        'header-home-label' => ['page'=>'Header','label'=>'Home navigation label','default'=>'Home'],
        'header-movies-label' => ['page'=>'Header','label'=>'Movies navigation label','default'=>'Movies'],
        'header-series-label' => ['page'=>'Header','label'=>'Series navigation label','default'=>'Series'],
        'header-premium-label' => ['page'=>'Header','label'=>'Premium navigation label','default'=>'Premium'],
        'header-free-label' => ['page'=>'Header','label'=>'Free navigation label','default'=>'Free'],
        'header-about-label' => ['page'=>'Header','label'=>'About navigation label','default'=>'About'],
        'footer-about-label' => ['page'=>'Footer','label'=>'About link label','default'=>'About Gomovie'],
        'footer-browse-label' => ['page'=>'Footer','label'=>'Browse link label','default'=>'Explore catalog'],
        'footer-publisher-label' => ['page'=>'Footer','label'=>'Publisher link label','default'=>'Publisher'],
    ];

    public static function slot(string $key): string
    {
        if (!isset(self::SLOTS[$key])) return '';

        // Request-scoped lookup avoids stale static state in Octane and across tests.
        $request = request();
        $values = $request->attributes->get('gm.studio.slot.overrides');
        if (!is_array($values)) {
            $values = Schema::hasTable('studio_content_slots')
                ? DB::table('studio_content_slots')->pluck('content','key')->all() : [];
            $request->attributes->set('gm.studio.slot.overrides',$values);
        }

        return array_key_exists($key,$values)
            ? (string)$values[$key]
            : self::SLOTS[$key]['default'];
    }

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
            ?? (object) ['family'=>'Instrument Sans','base_size'=>16,'heading_scale'=>100,
                'weight'=>600,'heading_variant'=>'default','body_variant'=>'default',
                'button_variant'=>'default','custom_font_key'=>null];
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
        $headingVariant = match($data->heading_variant ?? 'default') {
            'tight'=>'letter-spacing:-.03em;',
            'uppercase'=>'text-transform:uppercase;letter-spacing:.04em;',
            'classic'=>'font-style:italic;',
            default=>'',
        };
        $bodyVariant = match($data->body_variant ?? 'default') {
            'compact'=>'line-height:1.5;',
            'relaxed'=>'line-height:1.85;',
            default=>'',
        };
        $buttonVariant = match($data->button_variant ?? 'default') {
            'medium'=>'font-weight:500;',
            'bold'=>'font-weight:800;',
            default=>'',
        };
        $variantCss='';
        if ($headingVariant!=='') $variantCss.='.gm-site h1,.gm-site h2,.gm-site h3,.gm-auth-body h1,.gm-auth-body h2,.gm-auth-body h3{'.$headingVariant.'}';
        if ($bodyVariant!=='') $variantCss.='.gm-site p,.gm-auth-body p{'.$bodyVariant.'}';
        if ($buttonVariant!=='') $variantCss.='.gm-site .gm-button,.gm-auth-body .gm-button{'.$buttonVariant.'}';

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
            .$scaleCss.$variantCss;
    }
}
