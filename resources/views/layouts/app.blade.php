<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090812">
<meta name="description" content="@yield('description', 'Discover licensed movies and TV series on Gomovie.')">
<title>@yield('title','Discover') — Gomovie</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('partials.feedback')
<style>{!! \App\Support\StudioAppearance::css() !!}</style>
</head>
<body class="gm-site min-h-screen text-white antialiased">
<a href="#main-content" class="gm-skip">Skip to content</a>
<header class="gm-site-header">
  <div class="gm-container gm-header-inner" x-data="{ mobileOpen:false }">
    <a class="gm-brand" href="{{ route('home') }}" aria-label="Gomovie homepage">
      <span class="gm-brand-mark" aria-hidden="true"><span>▶</span></span>
      <span class="gm-brand-copy"><strong>GO<span>MOVIE</span></strong><small>MOVIES · SERIES · MORE</small></span>
    </a>
    <button class="gm-mobile-toggle" type="button" @click="mobileOpen=!mobileOpen"
      :aria-expanded="mobileOpen.toString()" aria-controls="gm-main-nav" aria-label="Toggle menu">☰</button>
    <nav id="gm-main-nav" class="gm-main-nav" :class="mobileOpen ? 'is-open' : ''" aria-label="Main navigation">
      <a href="{{ route('home') }}" @class(['is-active'=>request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
      <a href="{{ route('browse',['type'=>'movie']) }}" @class(['is-active'=>request()->routeIs('browse')&&request('type')==='movie'])>Movies</a>
      <a href="{{ route('browse',['type'=>'series']) }}" @class(['is-active'=>request()->routeIs('browse')&&request('type')==='series'])>Series</a>
      <a href="{{ route('browse',['access'=>'premium']) }}" class="gm-gold-nav">♛ Premium</a>
      <a href="{{ route('browse',['access'=>'free']) }}">Free</a>
      <a href="{{ route('about') }}" @class(['is-active'=>request()->routeIs('about')])>About</a>
    </nav>
    <form class="gm-header-search" role="search" method="GET" action="{{ route('browse') }}">
      <label class="sr-only" for="gm-search">Search movies or series</label>
      <span aria-hidden="true">⌕</span>
      <input id="gm-search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Search movies & series">
    </form>
    <span class="gm-language-label" aria-label="Current language English">◎ EN</span>
    @auth
      <a href="{{ auth()->user()->hasVerifiedEmail() ? route('account.overview') : route('verification.notice') }}"
         class="gm-account-trigger" title="My account" aria-label="My account">♙</a>
    @else
      <a href="{{ route('login') }}" class="gm-account-trigger" title="Sign in" aria-label="Sign in">♙</a>
    @endauth
  </div>
</header>
<main id="main-content" class="gm-container gm-main-content">
  @yield('content')
</main>
<footer class="gm-site-footer">
  <div class="gm-container gm-footer-inner">
    <a class="gm-brand gm-brand-small" href="{{ route('home') }}"><span class="gm-brand-mark" aria-hidden="true"><span>▶</span></span><span class="gm-brand-copy"><strong>GO<span>MOVIE</span></strong><small>MOVIES · SERIES · MORE</small></span></a>
    <nav aria-label="Footer"><a href="{{ route('about') }}">About Gomovie</a><a href="{{ route('browse') }}">Explore catalog</a><a href="https://tonsamith.com">Publisher</a></nav>
    <span>© {{ date('Y') }} Gomovie · Licensed media only</span>
  </div>
</footer>
</body>
</html>
