<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0A0A0C">
<title>@yield('title', 'Sign in') — Gomovie</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A0A0C] font-sans text-white antialiased">
<main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
    <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-[#E50914]/12 blur-3xl"></div>
    <section class="relative z-10 w-full max-w-md">
        <a href="{{ route('home') }}" class="mb-9 block text-center text-3xl font-black tracking-tight text-[#E50914]">GO<span class="text-white">MOVIE</span></a>
        <div class="rounded-3xl border border-white/10 bg-zinc-900/90 px-6 py-9 shadow-2xl shadow-black/60 sm:px-9">
            @yield('content')
        </div>
        <p class="mt-6 text-center text-xs text-zinc-500">© {{ date('Y') }} Gomovie · <a class="hover:text-white" href="{{ route('home') }}">Back to home</a></p>
    </section>
</main>
</body>
</html>
