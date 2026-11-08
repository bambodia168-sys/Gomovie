@extends('layouts.app')
@section('title','Discover')
@section('content')
<div class="mb-8"><p class="text-xs uppercase tracking-[.2em] text-[#F5C518]">Discover</p><h1 class="mt-2 text-4xl font-black">Movies & TV Series</h1><p class="mt-3 text-sm text-zinc-400">Find licensed entertainment by title.</p></div>
<form method="GET" action="{{ route('browse') }}" class="mb-10 flex flex-wrap items-center gap-3">
  <label class="sr-only" for="browse-query">Search titles</label>
  <input id="browse-query" name="q" value="{{ $q }}" maxlength="100" placeholder="Search the catalog" class="min-w-52 flex-1 rounded-xl border border-white/15 bg-zinc-900 px-5 py-3 outline-none focus:border-[#E50914]">
  <label for="browse-type" class="sr-only">Content type</label>
  <select id="browse-type" name="type" class="rounded-xl border border-white/15 bg-zinc-900 px-4 py-3">
    @foreach(['all'=>'All titles','movie'=>'Movies','series'=>'TV series'] as $value=>$label)<option value="{{ $value }}" @selected($type===$value)>{{ $label }}</option>@endforeach
  </select>
  <button class="rounded-xl bg-[#E50914] px-6 py-3 font-semibold hover:bg-red-700">Search</button>
</form>
@if($type !== 'series')
  <section><h2 class="mb-5 text-xl font-semibold">Movies</h2><div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
  @forelse($movies as $item)@include('frontend.partials.card',['kind'=>'movie'])@empty
  <p class="col-span-full rounded-xl border border-dashed border-white/15 p-8 text-center text-zinc-400">No matching movies.</p>@endforelse</div>
  <div class="mt-8">{{ $movies->links() }}</div></section>
@endif
@if($type !== 'movie')
  <section class="{{ $type==='all' ? 'mt-12' : '' }}"><h2 class="mb-5 text-xl font-semibold">TV Series</h2>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">@forelse($series as $item)
    @include('frontend.partials.card',['kind'=>'series'])
    @empty<p class="col-span-full rounded-xl border border-dashed border-white/15 p-8 text-center text-zinc-400">No matching TV series.</p>@endforelse</div></section>
@endif
@endsection
