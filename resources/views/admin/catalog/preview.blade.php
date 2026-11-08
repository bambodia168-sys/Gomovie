@extends('layouts.admin')
@section('title', 'Preview title')
@section('content')
@php
  $kind = $type === 'movies' ? 'movie' : 'series';
  $poster = \App\Support\CatalogArtwork::url($title->poster_path, 'w500');
  $backdrop = \App\Support\CatalogArtwork::url($title->backdrop_path, 'w780');
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <a href="{{ route('admin.catalog.index', $type) }}" class="text-sm font-semibold text-[#F5C518] hover:underline">← Back to {{ $type }}</a>
  <a href="{{ route('admin.catalog.edit', [$type, $title->id]) }}" class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold hover:bg-red-700">Edit title</a>
</div>
<article class="overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/70">
  @if($backdrop)
    <div class="relative h-36 overflow-hidden sm:h-64">
      <img src="{{ $backdrop }}" alt="" loading="lazy" class="h-full w-full object-cover opacity-65" onerror="this.hidden=true">
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-zinc-950 to-transparent"></div>
    </div>
  @endif
  <div class="grid gap-7 p-5 sm:grid-cols-[180px_minmax(0,1fr)] sm:p-8">
    <div class="overflow-hidden rounded-xl border border-white/15 bg-zinc-950 sm:self-start" style="aspect-ratio:2/3">
      @if($poster)
        <img src="{{ $poster }}" alt="{{ $title->title }} artwork preview" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
        <div hidden class="grid h-full place-items-center text-sm text-zinc-500">Poster unavailable</div>
      @else
        <div class="grid h-full place-items-center text-sm text-zinc-500">No poster artwork</div>
      @endif
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">Private staff preview · {{ $kind }}</p>
      <h2 class="mt-3 text-3xl font-black tracking-tight">{{ $title->title }}</h2>
      <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-zinc-300">
        <span class="rounded-full border border-white/20 px-3 py-1 font-bold capitalize">{{ $title->status }}</span>
        <span>{{ $title->is_premium ? 'Premium metadata' : 'Free metadata' }}</span>
        <span>{{ $type === 'movies' ? ($title->release_year ?? 'Year unavailable') : ($title->first_air_date?->format('Y') ?? 'Year unavailable') }}</span>
        @if($title->country_code)<span>{{ $title->country_code }}</span>@endif
        @if($type === 'movies' && $title->runtime_minutes)<span>{{ $title->runtime_minutes }} minutes</span>@endif
      </div>
      <p class="mt-6 max-w-3xl whitespace-pre-line text-sm leading-7 text-zinc-300">{{ $title->overview ?: 'No synopsis has been provided.' }}</p>
      <p class="mt-6 rounded-xl border border-amber-600/25 bg-amber-900/10 p-4 text-xs leading-6 text-amber-100">
        Staff-only metadata preview. TMDB artwork requires appropriate licensing and attribution. This page grants no video playback or distribution rights.
      </p>
      @if($title->status === 'published')
        <a href="{{ route('watch.show', [$kind, $title->slug]) }}" class="mt-5 inline-flex text-sm font-semibold text-[#F5C518] hover:underline">View public detail page →</a>
      @endif
    </div>
  </div>
  @if($type === 'series')
    <div class="border-t border-white/10 p-5 sm:p-8">
      <h3 class="text-xl font-semibold">Seasons and episodes</h3>
      @forelse($title->seasons as $season)
        <div class="mt-5 rounded-xl border border-white/10 bg-black/25 p-4">
          <h4 class="font-semibold">Season {{ $season->season_number }}: {{ $season->title }}</h4>
          <ul class="mt-3 space-y-2 text-sm text-zinc-400">
            @foreach($season->episodes as $episode)
              <li class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 py-2"><span>Episode {{ $episode->episode_number }} · {{ $episode->title }}</span><span class="text-xs capitalize">{{ $episode->status }}</span></li>
            @endforeach
          </ul>
        </div>
      @empty
        <p class="mt-3 text-sm text-zinc-400">No seasons have been added yet.</p>
      @endforelse
    </div>
  @endif
</article>
@endsection
