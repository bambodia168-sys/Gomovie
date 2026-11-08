<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090812">
<title>@yield('title','Sign in') — Gomovie</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="gm-auth-body text-white antialiased">
<a href="#auth-content" class="gm-skip">Skip to sign-in form</a>
<div class="gm-auth-atmosphere" aria-hidden="true"><span class="gm-auth-orbit gm-orbit-one"></span><span class="gm-auth-orbit gm-orbit-two"></span></div>
<div class="gm-auth-container">
  <header class="gm-auth-topbar"><a href="{{ route('home') }}" class="gm-brand">
      <span class="gm-brand-mark" aria-hidden="true"><span>▶</span></span>
      <span class="gm-brand-copy"><strong>GO<span>MOVIE</span></strong><small>MOVIES · SERIES · MORE</small></span>
    </a>
    <a href="{{ route('home') }}" class="gm-auth-back">← Back to homepage</a>
  </header>
  <main id="auth-content" class="gm-auth-columns">
    <div class="gm-auth-intro">
      <p class="gm-eyebrow">DISCOVER · SAVE · ENJOY</p>
      <h1>A world of stories.<br><em>One place to explore.</em></h1>
      <p>Explore published movies and series, find new favorites, and keep your personal watchlist organized.</p>
      <div class="gm-auth-feature-list">
        <div><span aria-hidden="true">▣</span><p><strong>Explore the catalog</strong><small>Browse films, series and episodes</small></p></div>
        <div><span aria-hidden="true">♡</span><p><strong>Save your favorites</strong><small>Pick up where your collection left off</small></p></div>
        <div><span aria-hidden="true">♧</span><p><strong>Access with care</strong><small>Streaming opens after rights and delivery approval</small></p></div>
      </div>
    </div>
    <section class="gm-auth-card" aria-label="@yield('title','Account') form">
      <div class="gm-auth-card-mark" aria-hidden="true">▶</div>
      @yield('content')
    </section>
  </main>
  <footer class="gm-auth-footer">
    <span>© {{ date('Y') }} Gomovie</span>
    <span>Published catalogs · Personal watchlists · Secure accounts</span>
  </footer>
</div>
</body>
</html>
