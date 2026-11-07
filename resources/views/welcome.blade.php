<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0A0A0C">
  <meta name="description" content="Gomovie — Discover movies and series.">
  <title>Gomovie — Movies & Series</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A0A0C] text-white antialiased">
  <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
    <a href="/" class="text-2xl font-black text-[#E50914]" aria-label="Gomovie home">GO<span class="text-white">MOVIE</span></a>
    <nav aria-label="Main navigation"><a href="/api/v1/movies" class="rounded-lg border border-white/20 px-4 py-2 text-sm hover:border-[#E50914]">Explore catalog</a></nav>
  </header>
  <main class="mx-auto flex min-h-[65vh] max-w-7xl flex-col justify-center px-6 py-20">
    <p class="mb-5 w-fit rounded-full border border-[#F5C518]/30 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-[#F5C518]">Licensed streaming platform</p>
    <h1 class="max-w-4xl text-5xl font-black tracking-tight sm:text-7xl">Stories worth <span class="text-[#E50914]">watching.</span></h1>
    <p class="mt-7 max-w-xl text-lg leading-relaxed text-zinc-400">Discover movies and series, save favorites, and enjoy secure streaming when a licensed catalog is available.</p>
    <div class="mt-9 flex flex-wrap gap-3"><a href="/api/v1/movies" class="rounded-xl bg-[#E50914] px-6 py-3 font-semibold text-white hover:bg-red-700">Browse movies</a><a href="/api/v1/series" class="rounded-xl border border-zinc-700 px-6 py-3 font-semibold hover:border-[#F5C518]">Explore series</a></div>
  </main>
  <footer class="mx-auto flex max-w-7xl justify-between border-t border-zinc-800 px-6 py-6 text-xs text-zinc-500"><span>© {{ date('Y') }} Gomovie</span><a href="https://tonsamith.com" rel="noopener noreferrer">Ton Samith</a></footer>
</body></html>
