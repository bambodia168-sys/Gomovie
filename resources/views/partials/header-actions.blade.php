{{-- Shared guest and role-aware account controls. All privileged links are
     shown only when the existing server-side role and verification rules allow. --}}
@php
  $navUser=auth()->user();
  $studioStaff=\App\Support\StudioNavigation::staff($navUser);
  $studioAdmin=\App\Support\StudioNavigation::administrator($navUser);
  $selectedLocale=\App\Support\StudioNavigation::locale();
  $languages=\App\Support\StudioNavigation::LANGUAGES;
@endphp
<div class="gm-header-tools" x-data="{profileOpen:false,localeOpen:false}"
     @keydown.escape.window="profileOpen=false;localeOpen=false">
  <div class="gm-header-popover-wrap">
    <button type="button" class="gm-header-tool gm-header-language"
            @click="localeOpen=!localeOpen;profileOpen=false"
            :aria-expanded="localeOpen.toString()" aria-controls="gm-language-popover"
            aria-label="{{ \App\Support\StudioNavigation::word('language') }}">
      <span aria-hidden="true">{{ $languages[$selectedLocale]['flag'] }}</span>
      <span class="gm-header-locale-code">{{ strtoupper($selectedLocale) }}</span>
      <svg aria-hidden="true" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div id="gm-language-popover" class="gm-header-popover gm-locale-popover" x-cloak x-show="localeOpen"
         x-transition.origin.top.right @click.outside="localeOpen=false" role="group"
         aria-label="{{ \App\Support\StudioNavigation::word('language') }}">
      <div class="gm-menu-title">{{ \App\Support\StudioNavigation::word('language') }}</div>
      <div class="gm-locale-options">
        @foreach($languages as $code=>$option)
          <form action="{{ route('locale.change') }}" method="POST">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button type="submit" @class(['gm-menu-link','is-selected'=>$selectedLocale===$code])
                    lang="{{ $code }}" @if($selectedLocale===$code) aria-current="true" @endif>
              <span class="gm-flag" aria-hidden="true">{{ $option['flag'] }}</span>
              <span>{{ $option['name'] }}</span>
              @if($selectedLocale===$code)<span class="gm-menu-check" aria-hidden="true">✓</span>@endif
            </button>
          </form>
        @endforeach
      </div>
      <small class="gm-locale-note">Untranslated catalog content remains in English.</small>
    </div>
  </div>

  <button type="button" class="gm-header-tool gm-theme-tool" data-gm-theme-toggle
          aria-label="{{ \App\Support\StudioNavigation::word('theme') }}"
          title="{{ \App\Support\StudioNavigation::word('theme') }}">
    <svg class="gm-theme-moon" aria-hidden="true" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 14.2A8.5 8.5 0 0 1 9.8 4a8.7 8.7 0 1 0 10.2 10.2z"/></svg>
    <svg class="gm-theme-sun" aria-hidden="true" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
  </button>

  <div class="gm-header-popover-wrap">
    <button type="button" class="gm-header-tool gm-profile-tool"
            @click="profileOpen=!profileOpen;localeOpen=false"
            :aria-expanded="profileOpen.toString()" aria-controls="gm-profile-popover"
            aria-label="{{ $navUser ? \App\Support\StudioNavigation::word('profile') : \App\Support\StudioNavigation::word('sign_in') }}">
      @auth
        @include('partials.account-avatar')
      @else
        <svg aria-hidden="true" viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="3.8"/><path d="M4.6 20v-1.3a7.4 7.4 0 0 1 14.8 0V20"/></svg>
      @endauth
    </button>
    <div id="gm-profile-popover" class="gm-header-popover gm-profile-popover" x-cloak x-show="profileOpen"
         x-transition.origin.top.right @click.outside="profileOpen=false" role="group"
         aria-label="{{ \App\Support\StudioNavigation::word('profile') }}">
      @auth
        <div class="gm-profile-summary">
          <span class="gm-profile-summary-avatar">@include('partials.account-avatar')</span>
          <span class="gm-profile-summary-text">
            <strong>{{ $navUser->name }}</strong>
            <small>{{ str_replace('_',' ',ucwords($navUser->role,'_')) }} · {{ $navUser->email }}</small>
          </span>
        </div>
        <div class="gm-menu-divider"></div>
        @if(!$navUser->hasVerifiedEmail())
          <a class="gm-menu-link" href="{{ route('verification.notice') }}">@include('partials.studio-icon',['name'=>'shield']) {{ \App\Support\StudioNavigation::word('verify') }}</a>
        @else
          <a class="gm-menu-link" href="{{ route('account.overview') }}">@include('partials.studio-icon',['name'=>'home']) {{ \App\Support\StudioNavigation::word('account') }}</a>
          <a class="gm-menu-link" href="{{ route('account.library',['section'=>'watchlist']) }}">@include('partials.studio-icon',['name'=>'heart']) {{ \App\Support\StudioNavigation::word('watchlist') }}</a>
          <a class="gm-menu-link" href="{{ route('account.library',['section'=>'history']) }}">@include('partials.studio-icon',['name'=>'history']) {{ \App\Support\StudioNavigation::word('history') }}</a>
          <a class="gm-menu-link" href="{{ route('account.settings') }}">@include('partials.studio-icon',['name'=>'settings']) {{ \App\Support\StudioNavigation::word('settings') }}</a>
          @if($studioStaff)
            <div class="gm-menu-divider"></div>
            <a class="gm-menu-link" href="{{ route('admin.dashboard') }}">@include('partials.studio-icon',['name'=>'grid']) {{ \App\Support\StudioNavigation::word('dashboard') }}</a>
            <a class="gm-menu-link" href="{{ route('upload-studio.index') }}">@include('partials.studio-icon',['name'=>'upload']) {{ \App\Support\StudioNavigation::word('upload') }}</a>
          @endif
          @if($studioAdmin)
            <a class="gm-menu-link" href="{{ route('admin.appearance.index') }}">@include('partials.studio-icon',['name'=>'appearance']) {{ \App\Support\StudioNavigation::word('appearance') }}</a>
            <a class="gm-menu-link" href="{{ route('admin.users.index') }}">@include('partials.studio-icon',['name'=>'users']) {{ \App\Support\StudioNavigation::word('users') }}</a>
          @endif
        @endif
        <div class="gm-menu-divider"></div>
        <form action="{{ route('logout') }}" method="POST">@csrf
          <button type="submit" class="gm-menu-link gm-menu-signout">@include('partials.studio-icon',['name'=>'external']) {{ \App\Support\StudioNavigation::word('sign_out') }}</button>
        </form>
      @else
        <div class="gm-menu-title">Welcome to Go Movie</div>
        <a class="gm-menu-link" href="{{ route('login') }}">@include('partials.studio-icon',['name'=>'home']) {{ \App\Support\StudioNavigation::word('sign_in') }}</a>
        <a class="gm-menu-link gm-menu-primary" href="{{ route('register') }}">@include('partials.studio-icon',['name'=>'plus']) {{ \App\Support\StudioNavigation::word('sign_up') }}</a>
        <div class="gm-menu-divider"></div>
        <a class="gm-menu-link" href="{{ route('browse') }}">@include('partials.studio-icon',['name'=>'film']) {{ \App\Support\StudioNavigation::word('browse') }}</a>
      @endauth
    </div>
  </div>
</div>
