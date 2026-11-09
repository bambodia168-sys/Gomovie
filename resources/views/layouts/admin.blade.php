<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0A0A0C">
<title>@yield('title','Studio') — Gomovie Studio</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('partials.feedback')

</head>
<body class="min-h-screen bg-[#0A0A0C] text-white antialiased" x-data="{menu:false}">
<div class="min-h-screen lg:flex">
  <aside :class="menu ? 'block' : 'hidden'" class="w-full shrink-0 border-r border-white/10 bg-[#111114] lg:sticky lg:top-0 lg:block lg:h-screen lg:w-64">
    <div class="border-b border-white/10 px-6 py-6"><a href="{{ route('home') }}" class="text-2xl font-black tracking-tight text-[#E50914]">GO<span class="text-white">MOVIE</span></a><p class="mt-1 text-xs tracking-widest text-zinc-500">MANAGEMENT STUDIO</p></div>
    <nav class="space-y-1 px-4 py-5 text-sm" aria-label="Studio">
      <p class="px-3 pb-2 pt-2 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-500">Overview</p>
      <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-4 py-3 {{ request()->routeIs('admin.dashboard') ? 'bg-[#E50914]/15 text-white' : 'text-zinc-400 hover:bg-white/5 hover:text-white' }}">◈ &nbsp; Dashboard</a>
      <p class="px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-500">Content</p>
      <a href="{{ route('admin.catalog.index','movies') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">▣ &nbsp; Movies</a>
      <a href="{{ route('admin.catalog.index','series') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">▤ &nbsp; TV Series</a>
      @if(in_array(auth()->user()->role,['admin','super_admin'],true))
      <a href="{{ route('upload-studio.index') }}" class="block rounded-xl px-4 py-3 {{ request()->routeIs('upload-studio.*') ? 'bg-[#E50914]/15 text-white' : 'text-zinc-300 hover:bg-white/5' }}">↑ &nbsp; Upload Studio</a>
      @endif
      <a href="{{ route('admin.media.index') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">▧ &nbsp; Media Library</a>
      @if(in_array(auth()->user()->role,['super_admin','admin'],true))
      <p class="px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-500">Administration</p>
      <a href="{{ route('admin.operations.index') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">◇ &nbsp; System Health</a>
      <a href="{{ route('admin.player-settings.edit') }}" class="block rounded-xl px-4 py-3 {{ request()->routeIs('admin.player-settings.*') ? 'bg-[#E50914]/15 text-white' : 'text-zinc-300 hover:bg-white/5' }}">▶ &nbsp; Player Settings</a>
      <a href="{{ route('admin.users.index') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">♙ &nbsp; User Manager</a>
      @endif
      <p class="px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-500">Workspace</p>
      <a href="{{ route('browse') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">↗ &nbsp; View website</a>
    </nav>
    <div class="px-7 pt-4 text-xs text-zinc-500">Video processing and paid billing remain disabled.</div>
  </aside>
  <div class="min-w-0 flex-1">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-[#111114] px-5 py-5 lg:px-9">
      <div class="flex items-center gap-3">
        <button type="button" class="rounded-lg border border-white/10 px-3 py-2 lg:hidden" @click="menu=!menu" :aria-expanded="menu.toString()" aria-label="Toggle studio navigation">☰</button>
        <div><p class="text-[11px] uppercase tracking-widest text-[#F5C518]">Gomovie Studio</p><h1 class="mt-1 text-xl font-bold">@yield('title','Dashboard')</h1></div>
      </div>
      <div class="flex items-center gap-3 text-sm"><span class="hidden text-zinc-400 sm:block">{{ auth()->user()->name }}</span><span class="rounded-full border border-[#F5C518]/20 px-3 py-1 text-xs font-medium capitalize text-[#F5C518]">{{ auth()->user()->role }}</span>
        <form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-white/15 px-3 py-2 hover:bg-white/5" type="submit">Sign out</button></form>
      </div>
    </header>
    <main class="mx-auto max-w-7xl p-5 lg:p-9">
      @if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-red-700/50 bg-red-950/40 p-4 text-sm text-red-200"><p class="mb-2 font-semibold">Please check your input.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      @yield('content')
    </main>
  </div>
</div>
</body></html>
