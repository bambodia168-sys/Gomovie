<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0A0A0C">
<title>@yield('title','Studio') — Gomovie Studio</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<style>
/* Scoped, self-contained styles: live aaPanel does not rebuild Vite for Blade-only releases. */
.gm-studio-toast {
  box-sizing:border-box;position:fixed;top:100px;right:22px;z-index:2500;
  display:flex;align-items:flex-start;gap:11px;
  width:max-content;max-width:min(390px,calc(100vw - 32px));
  padding:12px 14px;color:#d2ffe5;background:#132a21;
  border:1px solid rgba(77,202,138,.5);border-radius:12px;
  box-shadow:0 18px 46px rgba(0,0,0,.52);
  font-size:13px;font-weight:550;line-height:1.5;
}
.gm-studio-toast .gm-studio-toast-icon {flex:none;width:19px;height:19px;color:#5ee4a1;margin-top:1px}
.gm-studio-toast .gm-studio-toast-copy {flex:1;min-width:0;overflow-wrap:anywhere}
.gm-studio-toast .gm-studio-toast-close {
  flex:none;width:23px;height:23px;display:grid;place-items:center;
  margin:-2px -5px -2px 0;border:0;border-radius:6px;
  background:transparent;color:#b0d6c1;cursor:pointer;
}
.gm-studio-toast .gm-studio-toast-close:hover {background:rgba(255,255,255,.09);color:white}
.gm-studio-toast .gm-studio-toast-close svg {width:15px;height:15px}
@media(max-width:640px){
  .gm-studio-toast{right:10px;max-width:calc(100vw - 20px);padding:10px 12px;font-size:12px}
}
</style>
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
      <a href="{{ route('admin.upload-studio.index') }}" class="block rounded-xl px-4 py-3 {{ request()->routeIs('admin.upload-studio.*') ? 'bg-[#E50914]/15 text-white' : 'text-zinc-300 hover:bg-white/5' }}">↑ &nbsp; Upload Studio</a>
      @endif
      <a href="{{ route('admin.media.index') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">▧ &nbsp; Media Library</a>
      @if(in_array(auth()->user()->role,['super_admin','admin'],true))
      <p class="px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-500">Administration</p>
      <a href="{{ route('admin.operations.index') }}" class="block rounded-xl px-4 py-3 text-zinc-300 hover:bg-white/5">◇ &nbsp; System Health</a>
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
      @if(session('status'))
        <div x-data="{visible:true}"
             x-init="const bar = $el.closest('main')?.previousElementSibling; if (bar) $el.style.top = Math.ceil(bar.getBoundingClientRect().bottom + 12) + 'px'; setTimeout(() => visible = false, 5800)"
             x-show="visible" x-transition.opacity.duration.250ms
             role="status" aria-live="polite" class="gm-studio-toast">
          <svg aria-hidden="true" class="gm-studio-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m7 12 3 3 7-7"/></svg>
          <span class="gm-studio-toast-copy">{{ session('status') }}</span>
          <button type="button" @click="visible=false" class="gm-studio-toast-close" aria-label="Dismiss confirmation">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
      @endif
      @if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-red-700/50 bg-red-950/40 p-4 text-sm text-red-200"><p class="mb-2 font-semibold">Please check your input.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      @yield('content')
    </main>
  </div>
</div>
</body></html>
