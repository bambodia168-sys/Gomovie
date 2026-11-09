@extends('layouts.admin')
@section('title','Media Library')
@section('content')
<div class="mb-7 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-3xl font-bold">Media Library</h2><p class="mt-2 text-sm text-zinc-400">Licensed artwork and private video staging. Uploading video never activates playback.</p></div>
@if(in_array(auth()->user()->role,['admin','super_admin'],true))
<a href="{{ route('upload-studio.index') }}" class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-bold text-white hover:bg-red-600">↑ Upload a video</a>
@endif
</div>
<div class="grid gap-6 xl:grid-cols-2">
<section class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="mb-5 text-lg font-semibold">Upload artwork</h3>
  <form method="POST" action="{{ route('admin.media.artwork') }}" enctype="multipart/form-data" class="space-y-4">@csrf
    <div><label for="art-type" class="mb-2 block text-sm text-zinc-300">Content type</label><select id="art-type" name="type" required class="w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3"><option value="movie">Movie</option><option value="series">Series</option></select></div>
    <div><label for="art-id" class="mb-2 block text-sm text-zinc-300">Title ID</label><input id="art-id" name="title_id" required min="1" type="number" placeholder="Existing catalog ID" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3"></div>
    <div><label for="art-slot" class="mb-2 block text-sm text-zinc-300">Image placement</label><select id="art-slot" name="slot" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3"><option value="poster">Poster</option><option value="backdrop">Backdrop</option></select></div>
    <div><label for="art-image" class="mb-2 block text-sm text-zinc-300">JPG, PNG, WebP — up to 8 MB</label><input id="art-image" name="image" type="file" required accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3 text-sm"></div>
    <button type="submit" class="rounded-xl bg-[#E50914] px-5 py-3 font-semibold">Upload artwork</button>
  </form>
</section>
<section class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="mb-3 text-lg font-semibold">Upload Studio</h3>
  <p class="mb-5 text-sm leading-6 text-zinc-400">Choose a movie or TV episode, select a video, and upload. Your file is saved privately.</p>
  @if(in_array(auth()->user()->role,['admin','super_admin'],true))
    <a href="{{ route('upload-studio.index') }}" class="inline-flex rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold text-white">Open Upload Studio →</a>
  @else
    <p class="text-sm text-zinc-400">Private video upload requires administrator access.</p>
  @endif
</section>
</div>
<section class="mt-7 rounded-2xl border border-white/10 bg-zinc-900/60">
  <div class="border-b border-white/10 px-6 py-5"><h3 class="font-semibold">Uploaded videos</h3></div>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[580px] text-left text-sm">
      <thead class="text-xs uppercase text-zinc-500">
        <tr><th class="p-5">Video</th><th class="p-5">Uploaded by</th><th class="p-5">Size</th><th class="p-5">Status</th><th class="p-5">Uploaded</th><th class="p-5">Edit</th></tr>
      </thead>
      <tbody class="divide-y divide-white/10">
        @forelse($assets as $asset)
          <tr>
            <td class="p-5 font-semibold break-words">{{ $asset->original_name }}</td>
            <td class="p-5 text-zinc-400">{{ $asset->uploader }}</td>
            <td class="p-5 text-zinc-400">{{ number_format($asset->size_bytes / 1048576, 1) }} MB</td>
            <td class="p-5 text-zinc-300">{{ $asset->status === 'quarantined' ? 'Saved privately' : ucfirst($asset->status) }}</td>
            <td class="p-5 text-zinc-400">{{ $asset->created_at }}</td>
            <td class="p-5 text-xs">@if((int) $asset->uploaded_by === (int) auth()->id() && $asset->status === 'quarantined')<a class="font-semibold text-rose-300 hover:underline" href="{{ route('upload-studio.index', ['upload' => $asset->id]) }}">Edit upload →</a>@else<span class="text-zinc-600">—</span>@endif</td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-7 text-center text-zinc-500">No uploaded videos yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
<div class="mt-6">{{ $assets->links() }}</div>
<section class="mt-7 rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="font-semibold">Import TMDB metadata as a draft</h3>
  <p class="mt-2 text-xs text-zinc-400">Requires a configured API token. Metadata is not evidence of distribution rights.</p>
  <form method="POST" action="{{ route('admin.tmdb.import') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
    <div><label for="tmdb-type" class="mb-1 block text-xs text-zinc-400">Type</label><select id="tmdb-type" name="type" class="rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"><option value="movie">Movie</option><option value="series">Series</option></select></div>
    <div><label for="tmdb-id" class="mb-1 block text-xs text-zinc-400">TMDB ID</label><input id="tmdb-id" name="tmdb_id" type="number" min="1" required placeholder="e.g. 12345" class="rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
    <button type="submit" class="rounded-xl border border-[#F5C518]/40 px-5 py-3 font-semibold text-[#F5C518] hover:bg-white/5">Import draft</button>
  </form>
</section>
@endsection
