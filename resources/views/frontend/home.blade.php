@extends('layouts.app')
@section('title','Home')
@section('content')
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
<section class="mt-12"><div class="mb-6 flex items-center justify-between gap-2"><h2 class="text-2xl font-bold">Featured movies</h2><a href="{{ route('browse',['type'=>'movie']) }}" class="text-sm text-[#F5C518]">Browse all</a></div>
  @if($featuredMovies->isEmpty())<p class="rounded-2xl border border-dashed border-white/15 px-6 py-10 text-center text-zinc-400">No published movies are available yet.</p>@else
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">@foreach($featuredMovies as $item)@include('frontend.partials.card',['kind'=>'movie'])@endforeach</div>@endif
</section>
<section class="mt-14"><div class="mb-6 flex items-center justify-between gap-2"><h2 class="text-2xl font-bold">Explore TV series</h2><a href="{{ route('browse',['type'=>'series']) }}" class="text-sm text-[#F5C518]">Browse all</a></div>
  @if($featuredSeries->isEmpty())<p class="rounded-2xl border border-dashed border-white/15 px-6 py-10 text-center text-zinc-400">No published TV series are available yet.</p>@else
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">@foreach($featuredSeries as $item)@include('frontend.partials.card',['kind'=>'series'])@endforeach</div>@endif
</section>
@endsection
