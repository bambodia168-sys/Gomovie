@extends('layouts.admin')
@section('title', $title ? 'Edit title' : 'New title')
@section('content')
<div class="mb-7 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-3xl font-bold">{{ $title ? 'Edit' : 'Create' }} {{ $type === 'movies' ? 'movie' : 'series' }}</h2><p class="mt-2 text-sm text-zinc-400">Write and publish catalog metadata. Playback remains subject to verified distribution rights.</p></div>
<a href="{{ route('admin.catalog.index',$type) }}" class="text-sm text-[#F5C518]">← All {{ $type }}</a></div>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
<form id="title-form" action="{{ $title ? route('admin.catalog.update',[$type,$title->id]) : route('admin.catalog.store',$type) }}" method="POST" class="min-w-0 space-y-6">
@csrf @if($title) @method('PUT') @endif
<section class="space-y-5 rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="text-lg font-semibold">Main content</h3>
  <div><label for="title" class="mb-2 block text-sm text-zinc-300">Title</label><input required maxlength="255" id="title" name="title" value="{{ old('title',$title?->title ?? '') }}" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 outline-none focus:border-[#E50914]"></div>
  <div><label for="slug" class="mb-2 block text-sm text-zinc-300">URL slug</label><input pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="255" id="slug" name="slug" value="{{ old('slug',$title?->slug ?? '') }}" placeholder="Generated from title when empty" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 outline-none focus:border-[#E50914]"></div>
  <div><label for="overview" class="mb-2 block text-sm text-zinc-300">Synopsis</label><textarea id="overview" name="overview" maxlength="12000" rows="9" class="w-full resize-y rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 outline-none focus:border-[#E50914]">{{ old('overview',$title?->overview ?? '') }}</textarea></div>
  <div class="grid gap-4 sm:grid-cols-2">
     @if($type === 'movies')
       <div><label for="release_year" class="mb-2 block text-sm text-zinc-300">Release year</label><input required id="release_year" name="release_year" type="number" min="1888" max="2100" value="{{ old('release_year',$title?->release_year ?? '') }}" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
       <div><label for="runtime_minutes" class="mb-2 block text-sm text-zinc-300">Runtime in minutes</label><input id="runtime_minutes" name="runtime_minutes" type="number" min="1" max="1440" value="{{ old('runtime_minutes',$title?->runtime_minutes ?? '') }}" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
     @else
       <div><label for="first_air_date" class="mb-2 block text-sm text-zinc-300">First air date</label><input id="first_air_date" name="first_air_date" type="date" value="{{ old('first_air_date',$title?->first_air_date?->format('Y-m-d') ?? '') }}" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
     @endif
     <div><label for="country_code" class="mb-2 block text-sm text-zinc-300">Country ISO-2</label><input id="country_code" name="country_code" maxlength="2" value="{{ old('country_code',$title?->country_code ?? '') }}" placeholder="KH" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
     <div><label for="tmdb_id" class="mb-2 block text-sm text-zinc-300">TMDB reference ID</label><input id="tmdb_id" name="tmdb_id" type="number" min="1" value="{{ old('tmdb_id',$title?->tmdb_id ?? '') }}" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
  </div>
</section>
<section class="space-y-4 rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="text-lg font-semibold">Artwork references</h3>
  <p class="text-xs text-zinc-500">Upload artwork from Media Library or use approved TMDB relative paths. Artwork must be licensed for use.</p>
  @foreach(['poster_path'=>'Poster artwork','backdrop_path'=>'Backdrop artwork'] as $key=>$label)
  <div><label for="{{ $key }}" class="mb-2 block text-sm text-zinc-300">{{ $label }} path</label><input id="{{ $key }}" name="{{ $key }}" maxlength="1024" value="{{ old($key,$title?->{$key} ?? '') }}" placeholder="artwork/movie/..." class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 text-sm"></div>
  @endforeach
</section>
</form>
<aside class="space-y-5">
  <section class="space-y-5 rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
    <h3 class="font-semibold">Publishing</h3>
    <div><label for="status" class="mb-2 block text-sm text-zinc-300">Status</label><select id="status" name="status" form="title-form" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3">
      @foreach(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$title?->status ?? 'draft')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="is_premium" class="mb-2 block text-sm text-zinc-300">Visibility tier</label><select id="is_premium" name="is_premium" form="title-form" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3">
      <option value="1" @selected((string)old('is_premium',(int)($title?->is_premium ?? true))==='1')>Premium</option>
      <option value="0" @selected((string)old('is_premium',(int)($title?->is_premium ?? true))==='0')>Free</option></select></div>
    <button type="submit" form="title-form" class="w-full rounded-xl bg-[#E50914] px-4 py-3 font-semibold hover:bg-red-700">{{ $title ? 'Save changes' : 'Create title' }}</button>
    <p class="text-xs leading-5 text-zinc-500">Published catalog metadata does not grant stream access. Distribution rights and media assets are checked separately.</p>
  </section>
  @if($title)
    @if($type==='series')<a href="{{ route('admin.seasons.index',$title) }}" class="block rounded-xl border border-white/20 p-4 text-center text-sm font-semibold hover:bg-white/5">Manage seasons & episodes</a>@endif
    <a href="{{ route('admin.media.index') }}" class="block rounded-xl border border-[#F5C518]/30 p-4 text-center text-sm font-medium text-[#F5C518] hover:bg-white/5">Open Media Library</a>
    @if($title->status==='published')<a href="{{ route('watch.show',[$type === 'movies' ? 'movie' : 'series',$title->slug]) }}" class="block text-center text-sm text-[#F5C518]">View published page</a>@endif
    @if(in_array(auth()->user()->role,['admin','super_admin'],true) && $title->status === 'draft')
      <form method="POST" action="{{ route('admin.catalog.destroy',[$type,$title->id]) }}" onsubmit="return confirm('Delete this draft permanently?');">@csrf @method('DELETE')
        <button type="submit" class="w-full rounded-xl border border-red-600/40 p-3 text-sm text-red-300 hover:bg-red-950/30">Delete draft</button>
      </form>
    @endif
  @endif
</aside>
</div>
@endsection
