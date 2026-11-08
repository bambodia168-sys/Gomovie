<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0A0A0C">
<meta name="description" content="Discover licensed movies and TV series on Gomovie">
<title>@yield('title', 'Gomovie') — Gomovie</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A0A0C] text-white antialiased">
<header class="sticky top-0 z-40 border-b border-white/10 bg-[#0A0A0C]/95 backdrop-blur-xl">
<div class="mx-auto flex max-w-7xl flex-wrap items-center gap-5 px-5 py-4 sm:px-8">
    <a href="{{ route('home') }}" class="text-2xl font-black tracking-tight text-[#E50914]">GO<span class="text-white">MOVIE</span></a>
    <nav aria-label="Main navigation" class="flex gap-4 text-sm text-zinc-300">
        <a href="{{ route('home') }}" class="hover:text-white">Home</a>
        <a href="{{ route('browse') }}" class="hover:text-white">Movies & Series</a>
    </nav>
    <form class="order-3 w-full sm:order-none sm:ml-auto sm:w-56" method="GET" action="{{ route('browse') }}">
        <label class="sr-only" for="catalog-search">Search catalog</label>
        <input id="catalog-search" name="q" value="{{ request('q') }}" placeholder="Search movies & series" maxlength="100" class="w-full rounded-xl border border-white/15 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus:border-[#E50914]">
    </form>
    @auth
      @if(auth()->user()->hasVerifiedEmail())
        @if(in_array(auth()->user()->role, ['super_admin','admin','editor'], true))
          <a class="text-sm text-[#F5C518]" href="{{ route('admin.dashboard') }}">Studio</a>
        @endif
        <a class="rounded-xl border border-white/20 px-3 py-2 text-sm" href="{{ route('account.overview') }}">Account</a>
      @else <a href="{{ route('verification.notice') }}" class="text-sm text-[#F5C518]">Verify email</a> @endif
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-xs text-zinc-400 hover:text-white" type="submit">Sign out</button></form>
    @else
      <a class="text-sm text-zinc-300" href="{{ route('login') }}">Sign in</a>
      <a class="rounded-xl bg-[#E50914] px-4 py-2 text-sm font-semibold" href="{{ route('register') }}">Join</a>
    @endauth
</div></header>
<main class="mx-auto min-h-[72vh] max-w-7xl px-5 py-10 sm:px-8">
    @if(session('status'))<p role="status" class="mb-5 rounded-xl border border-emerald-700/40 bg-emerald-950/50 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>@endif
    @yield('content')
</main>
<footer class="border-t border-white/10 px-5 py-8 text-center text-xs text-zinc-500">© {{ date('Y') }} Gomovie. Licensed media only. <a href="https://tonsamith.com" class="hover:text-white">Ton Samith</a></footer>
</body></html>
