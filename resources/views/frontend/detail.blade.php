@extends('layouts.app')
@section('title', $title->title)
@section('content')
<a href="{{ route('browse') }}" class="text-sm text-[#F5C518]">← Back to catalog</a>
<div class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
<div>
  <p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">{{ $type==='movie' ? 'Feature film' : 'TV series' }}</p>
  <h1 class="mt-3 text-5xl font-black tracking-tight">{{ $title->title }}</h1>
  <div class="mt-5 flex flex-wrap items-center gap-3 text-sm text-zinc-400">
    <span class="rounded-lg border border-white/20 px-3 py-1">{{ $title->is_premium ? 'Premium' : 'Free access' }}</span>
    <span>{{ $type==='movie' ? ($title->release_year ?? 'Movie') : (optional($title->first_air_date)->format('Y') ?? 'Series') }}</span>
    @if($type==='movie' && $title->runtime_minutes)<span>{{ $title->runtime_minutes }} min</span>@endif
  </div>
  <p class="mt-8 max-w-3xl whitespace-pre-line text-base leading-8 text-zinc-300">{{ $title->overview ?: 'No synopsis has been added yet.' }}</p>
  <div class="mt-8 rounded-2xl border border-[#F5C518]/20 bg-zinc-900/60 p-5">
    <p class="font-semibold text-[#F5C518]">Playback access</p>
    <p class="mt-2 text-sm leading-6 text-zinc-400">Only licensed, approved content can be streamed. Playback is not available from this page until the media engine and edge authorization are accepted.</p>
  </div>
  @if($type==='series')
    <h2 class="mb-5 mt-10 text-2xl font-bold">Seasons & episodes</h2>
    @forelse($title->seasons()->orderBy('season_number')->with(['episodes'=>fn ($q)=>$q->where('status','published')])->get() as $season)
      <section class="mb-5 rounded-2xl border border-white/10 bg-zinc-900/50 p-5"><h3 class="font-semibold">Season {{ $season->season_number }} — {{ $season->title }}</h3>
        <ul class="mt-4 space-y-3 text-sm text-zinc-300">@forelse($season->episodes as $episode)<li class="flex justify-between gap-3 border-t border-white/10 pt-3"><span>{{ $episode->episode_number }}. {{ $episode->title }}</span><span class="text-zinc-500">{{ $episode->runtime_minutes ? $episode->runtime_minutes.' min' : '—' }}</span></li>@empty<li class="text-zinc-500">No published episodes yet.</li>@endforelse</ul>
      </section>
    @empty<p class="text-sm text-zinc-500">No seasons available.</p>@endforelse
  @endif
</div>
<aside class="rounded-2xl border border-white/10 bg-zinc-900/50 p-6">
  <h2 class="font-semibold">Title details</h2><dl class="mt-5 space-y-4 text-sm">
    <div><dt class="text-zinc-500">Country</dt><dd class="mt-1">{{ $title->country_code ?: 'Not specified' }}</dd></div>
    <div><dt class="text-zinc-500">Access tier</dt><dd class="mt-1">{{ $title->is_premium ? 'Premium' : 'Free' }}</dd></div>
    <div><dt class="text-zinc-500">Status</dt><dd class="mt-1 capitalize">{{ $title->status }}</dd></div>
  </dl>
</aside>
</div>
@endsection
