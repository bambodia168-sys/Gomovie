@extends('layouts.app')
@section('title','Home')
@section('content')
@if($heroTitles->isNotEmpty())
<section x-data="{slide:0, count:{{ $heroTitles->count() }}}" aria-label="Featured titles" class="relative overflow-hidden rounded-3xl border border-white/10 bg-[#141416] shadow-2xl">
  @foreach($heroTitles as $index=>$entry)
  @php
     $feature = $entry['item'];
     $featureType = $entry['kind'];
     $path = (string) ($feature->backdrop_path ?: $feature->poster_path ?: '');
     $backdrop = '';
     if (preg_match('#^/[A-Za-z0-9_.\/-]+\.(?:jpg|jpeg|png|webp)$#i', $path)) {
         $backdrop = 'https://image.tmdb.org/t/p/original'.$path;
     } elseif (preg_match('#^artwork/[A-Za-z0-9_.\/-]+$#', $path)) {
         $backdrop = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
     }
  @endphp
  <article x-cloak x-show="slide==={{ $index }}" x-transition.opacity.duration.350ms class="relative flex min-h-[430px] items-center p-7 sm:min-h-[540px] sm:p-12 lg:p-16">
    @if($backdrop)<img class="absolute inset-0 h-full w-full object-cover opacity-40" src="{{ $backdrop }}" alt="" aria-hidden="true" fetchpriority="{{ $index===0 ? 'high' : 'low' }}">@endif
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-r from-black via-black/75 to-transparent"></div>
    <div class="relative z-10 max-w-2xl">
      <p class="text-xs font-bold uppercase tracking-[.25em] text-[#F5C518]">{{ $featureType==='movie' ? 'Featured movie' : 'Featured series' }} · {{ $feature->is_premium ? 'Premium' : 'Free' }}</p>
      <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-6xl">{{ $feature->title }}</h1>
      <p class="mt-5 line-clamp-3 max-w-xl text-sm leading-relaxed text-zinc-300 sm:text-base">{{ $feature->overview ?: 'Explore this title in the Gomovie catalog.' }}</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('watch.show',[$featureType,$feature->slug]) }}" class="rounded-xl bg-[#E50914] px-6 py-3.5 text-sm font-bold hover:bg-red-700">View details</a>
        <a href="{{ route('browse') }}" class="rounded-xl border border-white/25 bg-black/25 px-6 py-3.5 text-sm font-bold hover:bg-white/5">Explore catalog</a>
      </div>
    </div>
  </article>
  @endforeach
  @if($heroTitles->count()>1)
  <div class="absolute bottom-5 right-5 z-20 flex items-center gap-2 sm:bottom-8 sm:right-8">
    <button type="button" @click="slide=(slide+count-1)%count" aria-label="Previous featured title" class="rounded-full border border-white/30 bg-black/60 p-3 hover:bg-black">‹</button>
    @foreach($heroTitles as $index=>$entry)
      <button type="button" @click="slide={{ $index }}" aria-label="Show featured title {{ $index+1 }}" :aria-current="slide==={{ $index }} ? 'true' : 'false'" :class="slide==={{ $index }} ? 'w-7 bg-[#E50914]' : 'w-2 bg-white/60'" class="h-2 rounded-full transition-all"></button>
    @endforeach
    <button type="button" @click="slide=(slide+1)%count" aria-label="Next featured title" class="rounded-full border border-white/30 bg-black/60 p-3 hover:bg-black">›</button>
  </div>
  @endif
</section>
@else
<section class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#2b0b12] via-[#141416] to-[#0A0A0C] px-7 py-16 sm:px-12 lg:py-24">
  <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-96 w-96 rounded-full bg-[#E50914]/20 blur-3xl"></div>
  <div class="relative z-10 max-w-2xl">
    <p class="text-xs font-bold uppercase tracking-[.25em] text-[#F5C518]">Gomovie originals & licensed entertainment</p>
    <h1 class="mt-5 text-5xl font-black leading-[1.05] tracking-tight sm:text-7xl">Your next story <span class="text-[#E50914]">starts here.</span></h1>
    <p class="mt-6 max-w-xl text-lg leading-relaxed text-zinc-300">Explore a curated catalog of movies and television series. Find favorites and discover what's next.</p>
    <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('browse') }}" class="rounded-xl bg-[#E50914] px-6 py-3.5 text-sm font-bold shadow-lg shadow-red-900/20 hover:bg-red-700">Browse catalog</a>
    @guest<a href="{{ route('register') }}" class="rounded-xl border border-white/25 px-6 py-3.5 text-sm font-bold hover:bg-white/5">Create account</a>@endguest</div>
  </div>
</section>
@endif
<section class="mt-12"><div class="mb-6 flex items-center justify-between gap-2"><h2 class="text-2xl font-bold">Featured movies</h2><a href="{{ route('browse',['type'=>'movie']) }}" class="text-sm text-[#F5C518]">Browse all</a></div>
  @if($featuredMovies->isEmpty())<p class="rounded-2xl border border-dashed border-white/15 px-6 py-10 text-center text-zinc-400">No published movies are available yet.</p>@else
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">@foreach($featuredMovies as $item)@include('frontend.partials.card',['kind'=>'movie'])@endforeach</div>@endif
</section>
<section class="mt-14"><div class="mb-6 flex items-center justify-between gap-2"><h2 class="text-2xl font-bold">Explore TV series</h2><a href="{{ route('browse',['type'=>'series']) }}" class="text-sm text-[#F5C518]">Browse all</a></div>
  @if($featuredSeries->isEmpty())<p class="rounded-2xl border border-dashed border-white/15 px-6 py-10 text-center text-zinc-400">No published TV series are available yet.</p>@else
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">@foreach($featuredSeries as $item)@include('frontend.partials.card',['kind'=>'series'])@endforeach</div>@endif
</section>
@endsection
