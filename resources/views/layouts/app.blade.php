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
@include('partials.brand-head')
@include('partials.header-ui')
<style>{!! \App\Support\StudioAppearance::css() !!}</style>
</head>
<body class="gm-site min-h-screen text-white antialiased">
<a href="#main-content" class="gm-skip">Skip to content</a>
<header class="gm-site-header">
  <div class="gm-container gm-header-inner" x-data="{ mobileOpen:false }">
    <a class="gm-brand" href="{{ route('home') }}" aria-label="Go Movie homepage">
      @include('partials.brand-logo')
    </a>
    <button class="gm-mobile-toggle" type="button" @click="mobileOpen=!mobileOpen"
      :aria-expanded="mobileOpen.toString()" aria-controls="gm-main-nav" aria-label="Toggle menu">☰</button>
    <nav id="gm-main-nav" class="gm-main-nav" :class="mobileOpen ? 'is-open' : ''" aria-label="Main navigation">
      <a href="{{ route('home') }}" @class(['is-active'=>request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>{{ \App\Support\StudioNavigation::navLabel('home', \App\Support\StudioAppearance::slot('header-home-label')) }}</a>
      <a href="{{ route('browse',['type'=>'movie']) }}" @class(['is-active'=>request()->routeIs('browse')&&request('type')==='movie'])>{{ \App\Support\StudioNavigation::navLabel('movies', \App\Support\StudioAppearance::slot('header-movies-label')) }}</a>
      <a href="{{ route('browse',['type'=>'series']) }}" @class(['is-active'=>request()->routeIs('browse')&&request('type')==='series'])>{{ \App\Support\StudioNavigation::navLabel('series', \App\Support\StudioAppearance::slot('header-series-label')) }}</a>
      <a href="{{ route('browse',['access'=>'premium']) }}" class="gm-gold-nav">♛ {{ \App\Support\StudioNavigation::navLabel('premium', \App\Support\StudioAppearance::slot('header-premium-label')) }}</a>
      <a href="{{ route('browse',['access'=>'free']) }}">{{ \App\Support\StudioNavigation::navLabel('free', \App\Support\StudioAppearance::slot('header-free-label')) }}</a>
      <a href="{{ route('about') }}" @class(['is-active'=>request()->routeIs('about')])>{{ \App\Support\StudioNavigation::navLabel('about', \App\Support\StudioAppearance::slot('header-about-label')) }}</a>
    </nav>
    <form class="gm-header-search" role="search" method="GET" action="{{ route('browse') }}">
      <label class="sr-only" for="gm-search">Search movies or series</label>
      <span aria-hidden="true">⌕</span>
      <input id="gm-search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Search movies & series">
    </form>
    @include('partials.header-actions')
  </div>
</header>
<main id="main-content" class="gm-container gm-main-content">
  @yield('content')
</main>
<footer class="gm-site-footer">
  <div class="gm-container gm-footer-inner">
    <a class="gm-brand gm-brand-small" href="{{ route('home') }}" aria-label="Go Movie homepage">@include('partials.brand-logo')</a>
    <nav aria-label="Footer"><a href="{{ route('about') }}">{{ \App\Support\StudioAppearance::slot('footer-about-label') }}</a><a href="{{ route('browse') }}">{{ \App\Support\StudioAppearance::slot('footer-browse-label') }}</a><a href="https://tonsamith.com">{{ \App\Support\StudioAppearance::slot('footer-publisher-label') }}</a></nav>
    <span>© {{ date('Y') }} Gomovie · Licensed media only</span>
  </div>
</footer>
</body>
</html>
