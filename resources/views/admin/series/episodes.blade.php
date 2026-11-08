@extends('layouts.admin')
@section('title','Seasons & Episodes')
@section('content')
<div class="mb-7 flex flex-wrap items-start justify-between gap-4">
  <div><p class="text-xs uppercase tracking-widest text-[#F5C518]">TV Series Studio</p><h2 class="mt-2 text-3xl font-bold">{{ $series->title }}</h2><p class="mt-2 text-zinc-400">Manage ordered seasons, episode metadata and publishing status.</p></div>
  <a href="{{ route('admin.catalog.edit',['series',$series->id]) }}" class="rounded-xl border border-white/20 px-4 py-3 text-sm">Edit series</a>
</div>
<section class="mb-7 rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
  <h3 class="text-lg font-semibold">Add season</h3>
  <form method="POST" action="{{ route('admin.seasons.store',$series) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
    <div><label for="new-season" class="mb-2 block text-xs text-zinc-400">Season number</label><input id="new-season" name="season_number" type="number" min="0" max="9999" required class="w-36 rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
    <div class="min-w-48 flex-1"><label for="season-title" class="mb-2 block text-xs text-zinc-400">Season title</label><input id="season-title" name="title" maxlength="255" required placeholder="Season One" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
    <button class="rounded-xl bg-[#E50914] px-5 py-3 font-semibold">Create season</button>
  </form>
</section>
@forelse($seasons as $season)
<section class="mb-7 overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/60">
  <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 px-6 py-5">
    <div><p class="text-xs text-[#F5C518]">SEASON {{ $season->season_number }}</p><h3 class="mt-1 text-xl font-bold">{{ $season->title }}</h3><p class="mt-1 text-xs text-zinc-500">{{ $season->episodes->count() }} episodes</p></div>
    <details class="relative"><summary class="cursor-pointer list-none rounded-xl border border-white/20 px-3 py-2 text-xl leading-none" aria-label="Manage season">⋮</summary>
    <div class="absolute right-0 z-20 mt-2 w-80 max-w-[80vw] rounded-xl border border-white/15 bg-zinc-950 p-4 shadow-2xl">
      <form method="POST" action="{{ route('admin.seasons.update',[$series,$season]) }}" class="space-y-3">@csrf @method('PATCH')
        <label class="block text-xs text-zinc-400" for="season-number-{{ $season->id }}">Season number</label><input id="season-number-{{ $season->id }}" type="number" name="season_number" value="{{ $season->season_number }}" min="0" required class="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2">
        <label class="block text-xs text-zinc-400" for="season-edit-{{ $season->id }}">Title</label><input id="season-edit-{{ $season->id }}" name="title" value="{{ $season->title }}" required class="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2">
        <button class="w-full rounded-lg bg-[#E50914] px-4 py-2 text-sm font-semibold">Save season</button>
      </form>
      @if($season->episodes->isEmpty() && in_array(auth()->user()->role,['admin','super_admin'],true))
      <form method="POST" class="mt-3" action="{{ route('admin.seasons.destroy',[$series,$season]) }}" onsubmit="return confirm('Delete empty season?');">@csrf @method('DELETE')<button type="submit" class="w-full rounded-lg border border-red-500/30 px-3 py-2 text-sm text-red-300">Delete empty season</button></form>
      @endif
    </div></details>
  </div>
  <div class="overflow-x-auto"><table class="w-full min-w-[580px] text-left text-sm">
    <thead class="text-xs uppercase text-zinc-500"><tr><th class="px-6 py-4">Episode</th><th class="px-6 py-4">Runtime</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-right">Actions</th></tr></thead>
    <tbody class="divide-y divide-white/10">
    @forelse($season->episodes as $episode)
    <tr><td class="px-6 py-4"><span class="text-zinc-500">{{ $episode->episode_number }}.</span> <strong>{{ $episode->title }}</strong></td>
        <td class="px-6 py-4 text-zinc-400">{{ $episode->runtime_minutes ? $episode->runtime_minutes.' min' : '—' }}</td>
        <td class="px-6 py-4 capitalize text-zinc-400">{{ $episode->status }}</td>
        <td class="px-6 py-4 text-right"><details class="relative inline-block text-left"><summary class="cursor-pointer list-none rounded-lg border border-white/15 px-3 py-1 text-lg" aria-label="Episode actions">⋮</summary>
            <div class="absolute right-0 z-20 mt-2 w-80 max-w-[85vw] space-y-3 rounded-xl border border-white/15 bg-zinc-950 p-4 text-left shadow-2xl">
              <form method="POST" action="{{ route('admin.episodes.update',[$series,$season,$episode]) }}" class="space-y-3">@csrf @method('PATCH')
                <label for="episode-no-{{ $episode->id }}" class="block text-xs text-zinc-400">Episode number</label><input id="episode-no-{{ $episode->id }}" name="episode_number" type="number" min="1" value="{{ $episode->episode_number }}" required class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">
                <label for="episode-title-{{ $episode->id }}" class="block text-xs text-zinc-400">Title</label><input id="episode-title-{{ $episode->id }}" name="title" value="{{ $episode->title }}" required class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">
                <label for="episode-overview-{{ $episode->id }}" class="block text-xs text-zinc-400">Synopsis</label><textarea id="episode-overview-{{ $episode->id }}" name="overview" rows="3" class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">{{ $episode->overview }}</textarea>
                <label for="episode-minutes-{{ $episode->id }}" class="block text-xs text-zinc-400">Runtime minutes</label><input id="episode-minutes-{{ $episode->id }}" name="runtime_minutes" type="number" min="1" value="{{ $episode->runtime_minutes }}" class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">
                <label for="episode-air-{{ $episode->id }}" class="block text-xs text-zinc-400">Air date</label><input id="episode-air-{{ $episode->id }}" name="air_date" type="date" value="{{ optional($episode->air_date)->format('Y-m-d') }}" class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">
                <input type="hidden" name="still_path" value="{{ $episode->still_path }}">
                <label for="episode-status-{{ $episode->id }}" class="block text-xs text-zinc-400">Status</label><select id="episode-status-{{ $episode->id }}" name="status" class="w-full rounded-lg border border-white/20 bg-zinc-900 px-3 py-2">@foreach(['draft','published','archived'] as $s)<option value="{{ $s }}" @selected($episode->status===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
                <button type="submit" class="w-full rounded-lg bg-[#E50914] px-3 py-2 text-sm font-semibold">Save episode</button>
              </form>
              @if($episode->status==='draft' && in_array(auth()->user()->role,['admin','super_admin'],true))
              <form method="POST" action="{{ route('admin.episodes.destroy',[$series,$season,$episode]) }}" onsubmit="return confirm('Delete draft episode?');">@csrf @method('DELETE')<button class="w-full rounded-lg border border-red-500/30 px-3 py-2 text-sm text-red-300">Delete draft</button></form>
              @endif
            </div></details></td></tr>
    @empty<tr><td colspan="4" class="px-6 py-7 text-center text-zinc-500">No episodes created.</td></tr>@endforelse</tbody>
  </table></div>
  <div class="border-t border-white/10 px-6 py-5">
    <details><summary class="cursor-pointer list-none text-sm font-semibold text-[#F5C518]">+ Add an episode</summary>
    <form method="POST" action="{{ route('admin.episodes.store',[$series,$season]) }}" class="mt-5 grid gap-4 md:grid-cols-3">@csrf
      <div><label class="mb-2 block text-xs text-zinc-400">Episode number</label><input type="number" name="episode_number" min="1" required class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"></div>
      <div class="md:col-span-2"><label class="mb-2 block text-xs text-zinc-400">Title</label><input name="title" required maxlength="255" class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"></div>
      <div class="md:col-span-3"><label class="mb-2 block text-xs text-zinc-400">Synopsis</label><textarea name="overview" rows="3" class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"></textarea></div>
      <div><label class="mb-2 block text-xs text-zinc-400">Minutes</label><input name="runtime_minutes" type="number" min="1" class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"></div>
      <div><label class="mb-2 block text-xs text-zinc-400">Air date</label><input name="air_date" type="date" class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"></div>
      <div><label class="mb-2 block text-xs text-zinc-400">Status</label><select name="status" class="w-full rounded-lg border border-white/20 bg-zinc-950 px-3 py-3"><option value="draft">Draft</option><option value="published">Published</option></select></div>
      <button type="submit" class="rounded-xl bg-[#E50914] px-5 py-3 font-semibold md:col-span-3">Create episode</button>
    </form></details>
  </div>
</section>
@empty
<p class="rounded-2xl border border-dashed border-white/15 p-9 text-center text-zinc-400">No seasons. Create a season above.</p>
@endforelse
@endsection
