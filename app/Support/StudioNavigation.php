<?php

namespace App\Support;

use App\Models\User;

final class StudioNavigation
{
    // Only languages exposed to viewers are selectable. Unsupported content
    // remains in English; do not claim untranslated movie copy is localized.
    public const LANGUAGES = [
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
        'zh' => ['name' => '中文', 'flag' => '🇨🇳'],
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
        'hi' => ['name' => 'हिन्दी', 'flag' => '🇮🇳'],
        'id' => ['name' => 'Bahasa Indonesia', 'flag' => '🇮🇩'],
        'ja' => ['name' => '日本語', 'flag' => '🇯🇵'],
        'km' => ['name' => 'ខ្មែរ', 'flag' => '🇰🇭'],
        'ko' => ['name' => '한국어', 'flag' => '🇰🇷'],
        'ms' => ['name' => 'Bahasa Melayu', 'flag' => '🇲🇾'],
        'th' => ['name' => 'ไทย', 'flag' => '🇹🇭'],
        'vi' => ['name' => 'Tiếng Việt', 'flag' => '🇻🇳'],
    ];

    private const WORDS = [
        'en' => ['sign_in'=>'Sign In','sign_up'=>'Sign Up','sign_out'=>'Sign Out','account'=>'My Account',
            'settings'=>'Account Settings','watchlist'=>'My List','history'=>'Watch History','dashboard'=>'Management Studio',
            'movies'=>'Movies','series'=>'Series','home'=>'Home','theme'=>'Light / Dark Mode',
            'language'=>'Language','appearance'=>'Appearance','users'=>'User Manager','upload'=>'Upload Studio',
            'browse'=>'Explore Catalog','verify'=>'Verify Email','profile'=>'Profile'],
        'zh' => ['sign_in'=>'登录','sign_up'=>'注册','sign_out'=>'退出登录','account'=>'我的账户','settings'=>'账户设置','watchlist'=>'我的片单','history'=>'观看记录','dashboard'=>'管理中心','movies'=>'电影','series'=>'剧集','home'=>'首页','theme'=>'明暗模式','language'=>'语言','browse'=>'浏览影片'],
        'fr' => ['sign_in'=>'Se connecter','sign_up'=>"S'inscrire",'sign_out'=>'Se déconnecter','account'=>'Mon compte','settings'=>'Paramètres','watchlist'=>'Ma liste','history'=>'Historique','dashboard'=>'Studio de gestion','movies'=>'Films','series'=>'Séries','home'=>'Accueil','theme'=>'Mode clair / sombre','language'=>'Langue','browse'=>'Explorer'],
        'hi' => ['sign_in'=>'साइन इन','sign_up'=>'साइन अप','sign_out'=>'साइन आउट','account'=>'मेरा खाता','settings'=>'खाता सेटिंग','watchlist'=>'मेरी सूची','history'=>'देखने का इतिहास','dashboard'=>'प्रबंधन स्टूडियो','movies'=>'फ़िल्में','series'=>'सीरीज़','home'=>'होम','theme'=>'लाइट / डार्क मोड','language'=>'भाषा','browse'=>'कैटलॉग देखें'],
        'id' => ['sign_in'=>'Masuk','sign_up'=>'Daftar','sign_out'=>'Keluar','account'=>'Akun Saya','settings'=>'Pengaturan Akun','watchlist'=>'Daftar Saya','history'=>'Riwayat Tonton','dashboard'=>'Studio Manajemen','movies'=>'Film','series'=>'Serial','home'=>'Beranda','theme'=>'Mode Terang / Gelap','language'=>'Bahasa','browse'=>'Jelajahi Katalog'],
        'ja' => ['sign_in'=>'ログイン','sign_up'=>'新規登録','sign_out'=>'ログアウト','account'=>'マイアカウント','settings'=>'アカウント設定','watchlist'=>'マイリスト','history'=>'視聴履歴','dashboard'=>'管理スタジオ','movies'=>'映画','series'=>'シリーズ','home'=>'ホーム','theme'=>'ライト / ダーク','language'=>'言語','browse'=>'作品を探す'],
        'km' => ['sign_in'=>'ចូលគណនី','sign_up'=>'ចុះឈ្មោះ','sign_out'=>'ចាកចេញ','account'=>'គណនីខ្ញុំ','settings'=>'ការកំណត់គណនី','watchlist'=>'បញ្ជីរបស់ខ្ញុំ','history'=>'ប្រវត្តិទស្សនា','dashboard'=>'ផ្ទាំងគ្រប់គ្រង','movies'=>'ភាពយន្ត','series'=>'ភាគបន្ត','home'=>'ទំព័រដើម','theme'=>'ពន្លឺ / ងងឹត','language'=>'ភាសា','browse'=>'ស្វែងរកភាពយន្ត'],
        'ko' => ['sign_in'=>'로그인','sign_up'=>'가입하기','sign_out'=>'로그아웃','account'=>'내 계정','settings'=>'계정 설정','watchlist'=>'내 목록','history'=>'시청 기록','dashboard'=>'관리 스튜디오','movies'=>'영화','series'=>'시리즈','home'=>'홈','theme'=>'라이트 / 다크 모드','language'=>'언어','browse'=>'카탈로그 탐색'],
        'ms' => ['sign_in'=>'Log Masuk','sign_up'=>'Daftar','sign_out'=>'Log Keluar','account'=>'Akaun Saya','settings'=>'Tetapan Akaun','watchlist'=>'Senarai Saya','history'=>'Sejarah Tontonan','dashboard'=>'Studio Pengurusan','movies'=>'Filem','series'=>'Siri','home'=>'Utama','theme'=>'Mod Cerah / Gelap','language'=>'Bahasa','browse'=>'Terokai Katalog'],
        'th' => ['sign_in'=>'เข้าสู่ระบบ','sign_up'=>'สมัครสมาชิก','sign_out'=>'ออกจากระบบ','account'=>'บัญชีของฉัน','settings'=>'ตั้งค่าบัญชี','watchlist'=>'รายการของฉัน','history'=>'ประวัติการรับชม','dashboard'=>'สตูดิโอจัดการ','movies'=>'ภาพยนตร์','series'=>'ซีรีส์','home'=>'หน้าแรก','theme'=>'โหมดสว่าง / มืด','language'=>'ภาษา','browse'=>'สำรวจรายการ'],
        'vi' => ['sign_in'=>'Đăng nhập','sign_up'=>'Đăng ký','sign_out'=>'Đăng xuất','account'=>'Tài khoản của tôi','settings'=>'Cài đặt tài khoản','watchlist'=>'Danh sách của tôi','history'=>'Lịch sử xem','dashboard'=>'Trung tâm quản lý','movies'=>'Phim','series'=>'Phim bộ','home'=>'Trang chủ','theme'=>'Chế độ sáng / tối','language'=>'Ngôn ngữ','browse'=>'Khám phá'],
    ];

    public static function locale(): string
    {
        $locale = (string) session('gomovie.locale', 'en');
        return array_key_exists($locale, self::LANGUAGES) ? $locale : 'en';
    }

    public static function word(string $key): string
    {
        $locale = self::locale();
        return self::WORDS[$locale][$key] ?? self::WORDS['en'][$key] ?? $key;
    }

    public static function staff(?User $user): bool
    {
        return $user && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, ['editor', 'admin', 'super_admin'], true);
    }

    public static function administrator(?User $user): bool
    {
        return self::staff($user) && in_array($user->role, ['admin', 'super_admin'], true);
    }

    public static function landing(User $user): string
    {
        if (!$user->hasVerifiedEmail()) return route('verification.notice');
        return self::staff($user) ? route('admin.dashboard') : route('account.overview');
    }

    /** Keep post-login redirection strictly on a known local watch route. */
    public static function watchReturn(?string $candidate): ?string
    {
        if (!is_string($candidate) || strlen($candidate) > 500 || str_contains($candidate, "\n")
            || str_contains($candidate, "\r") || str_contains($candidate, '\\')) return null;
        $parts = parse_url($candidate);
        if (!is_array($parts) || isset($parts['scheme'], $parts['host']) || isset($parts['host'])
            || isset($parts['fragment']) || isset($parts['user'])) return null;
        $path = $parts['path'] ?? '';
        if (!preg_match('~\A/watch/(movie|series)/[a-z0-9-]+\z~D', $path)) return null;
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (!in_array($key, ['view', 'episode', 'autoplay'], true)) return null;
            if (!is_string($query[$key])) return null;
        }
        if (isset($query['view']) && !in_array($query['view'], ['widescreen','portrait'], true)) return null;
        if (isset($query['episode']) && !preg_match('/\A[1-9][0-9]{0,9}\z/D', $query['episode'])) return null;
        if (isset($query['autoplay']) && $query['autoplay'] !== '1') return null;
        return $path.($query ? '?'.http_build_query($query) : '');
    }
}
