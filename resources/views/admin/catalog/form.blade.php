@extends('layouts.admin')
@section('title', $title ? 'Edit title' : 'New title')
@section('content')
<div class="gm-page-heading">
<div><p class="text-xs uppercase tracking-widest text-amber-300">Catalog / {{ ucfirst($type) }}</p><h2>{{ $title ? 'Edit' : 'Create' }} {{ $type === 'movies' ? 'movie' : 'series' }}</h2><p>Write and publish catalog metadata. Playback remains subject to verified distribution rights.</p></div>
<div class="gm-page-actions">
<a href="{{ route('admin.catalog.index',$type) }}" class="gm-studio-button">@include('partials.studio-icon',['name'=>'grid']) All {{ $type }}</a>
@if($title)
  @if($type==='series')<a href="{{ route('admin.seasons.index',$title) }}" class="gm-studio-button">@include('partials.studio-icon',['name'=>'series']) Seasons & episodes</a>@endif
  <a href="{{ route('admin.media.index') }}" class="gm-studio-button">@include('partials.studio-icon',['name'=>'media']) Media Library</a>
  @if($title->status==='published')<a href="{{ route('watch.show',[$type==='movies'?'movie':'series',$title->slug]) }}" class="gm-studio-button" target="_blank" rel="noopener noreferrer">@include('partials.studio-icon',['name'=>'eye']) View page</a>@endif
@endif
<button type="submit" form="title-form" class="gm-studio-button is-primary">@include('partials.studio-icon',['name'=>'check']) {{ $title ? 'Save changes' : 'Create title' }}</button>
@if($title && in_array(auth()->user()->role,['admin','super_admin'],true) && $title->status==='draft')
  <form method="POST" action="{{ route('admin.catalog.destroy',[$type,$title->id]) }}">@csrf @method('DELETE')
    <button class="gm-studio-button is-danger" type="submit" data-studio-confirm="Delete this draft permanently?">@include('partials.studio-icon',['name'=>'trash']) Delete draft</button>
  </form>
@endif
</div></div>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
<form id="title-form" action="{{ $title ? route('admin.catalog.update',[$type,$title->id]) : route('admin.catalog.store',$type) }}" method="POST" enctype="multipart/form-data" class="min-w-0 space-y-6"
      data-catalog-artwork-url="{{ route('admin.media.artwork', [], false) }}"
      data-catalog-artwork-type="{{ $type === 'movies' ? 'movie' : 'series' }}">
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
  <p class="text-xs text-zinc-500">Upload a public JPG, PNG, or WebP up to 8 MB to fill the URL automatically, or paste an image URL or an existing artwork path.</p>
  @if($title)
    @php
      $posterPreview = \App\Support\CatalogArtwork::url($title->poster_path, 'w342');
      $backdropPreview = \App\Support\CatalogArtwork::url($title->backdrop_path, 'w780');
    @endphp
    <div class="flex items-start gap-4 rounded-xl border border-white/10 bg-black/25 p-3">
      <div class="h-36 w-24 shrink-0 overflow-hidden rounded-lg border border-white/15 bg-[#1b1420]">
        @if($posterPreview)
          <img src="{{ $posterPreview }}" alt="{{ $title->title }} poster reference" loading="lazy" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
          <span hidden class="flex h-full items-center justify-center px-2 text-center text-xs text-zinc-500">Artwork unavailable</span>
        @else
          <span class="flex h-full items-center justify-center px-2 text-center text-xs text-zinc-500">No poster reference</span>
        @endif
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-white">Current poster reference</p>
        <p class="mt-1 text-xs leading-5 text-zinc-400">Use artwork you have permission to display.</p>
        @if($backdropPreview)
          <img src="{{ $backdropPreview }}" alt="{{ $title->title }} backdrop reference" loading="lazy" class="mt-3 max-h-32 w-full rounded-lg object-cover" onerror="this.hidden=true">
        @endif
      </div>
    </div>
  @endif
  @foreach(['poster'=>'Poster', 'backdrop'=>'Backdrop'] as $slot => $label)
    @php($pathField = $slot.'_path')
    @php($uploadField = $slot.'_upload')
    <div data-catalog-artwork-field="{{ $slot }}">
      <label for="{{ $pathField }}" class="mb-2 block text-sm text-zinc-300">{{ $label }} artwork path</label>
      <div class="flex items-stretch gap-2">
        <input type="text" id="{{ $pathField }}" name="{{ $pathField }}" maxlength="1024"
               value="{{ old($pathField, \App\Support\CatalogArtwork::formReference($title?->{$pathField})) }}"
               placeholder="https://… or artwork/…"
               class="min-w-0 w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 text-sm">
        <button type="button" class="gm-studio-button shrink-0" aria-label="Upload {{ strtolower($label) }} artwork"
                data-catalog-artwork-open>
          @include('partials.studio-icon', ['name'=>'upload']) Upload
        </button>
        <input type="file" id="{{ $uploadField }}" name="{{ $uploadField }}" hidden
               aria-label="{{ $label }} artwork file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
      </div>
      <p class="mt-2 text-xs text-zinc-400" role="status" data-catalog-artwork-status hidden></p>
      @error($pathField)<p class="mt-2 text-sm text-rose-300" role="alert">{{ $message }}</p>@enderror
      @error($uploadField)<p class="mt-2 text-sm text-rose-300" role="alert">{{ $message }}</p>@enderror
    </div>
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
    <p class="text-xs leading-5 text-zinc-500">Published catalog metadata does not grant stream access. Distribution rights and media assets are checked separately.</p>
  </section>

</aside>
</div>
<script>
(() => {
  const form = document.getElementById('title-form');
  const save = document.querySelector('[form="title-form"][type="submit"]');
  let pending = 0;

  form.addEventListener('submit', event => {
    if (pending > 0) event.preventDefault();
  });

  for (const field of form.querySelectorAll('[data-catalog-artwork-field]')) {
    const fileInput = field.querySelector('input[type="file"]');
    const reference = field.querySelector('input[type="text"]');
    const open = field.querySelector('[data-catalog-artwork-open]');
    const status = field.querySelector('[data-catalog-artwork-status]');

    open.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async () => {
      const image = fileInput.files[0];
      if (!image) return;
      fileInput.value = '';
      status.hidden = false;
      if (image.size > 8 * 1024 * 1024) {
        status.textContent = 'Choose an image up to 8 MB.';
        return;
      }

      const body = new FormData();
      body.append('type', form.dataset.catalogArtworkType);
      body.append('slot', field.dataset.catalogArtworkField);
      body.append('upload_only', '1');
      body.append('image', image);

      pending++;
      save.disabled = true;
      open.disabled = true;
      reference.readOnly = true;
      status.textContent = 'Uploading artwork…';

      try {
        const response = await fetch(form.dataset.catalogArtworkUrl, {
          method: 'POST',
          headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
          body,
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.url) {
          const message = response.status === 419 ? 'Your session expired. Sign in again to upload artwork.'
            : response.status === 413 ? 'The server rejected this file size. Choose a smaller image.'
            : result.errors?.image?.[0] ?? 'Artwork could not be uploaded. Choose the file again to retry.';
          throw new Error(message);
        }

        reference.value = result.url;
        status.textContent = 'Artwork uploaded. Save the title to attach it.';
      } catch (error) {
        status.textContent = error instanceof TypeError ? 'Connection interrupted. Choose the file again to retry.' : error.message;
      } finally {
        open.disabled = false;
        reference.readOnly = false;
        pending--;
        save.disabled = pending > 0;
      }
    });
  }
})();
</script>
@endsection
