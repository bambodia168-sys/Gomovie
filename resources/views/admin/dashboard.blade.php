@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="mb-7 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-3xl font-black tracking-tight">Content overview</h2><p class="mt-2 text-sm text-zinc-400">Real catalog data and operational activity.</p></div>
<a class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold text-white hover:bg-red-700" href="{{ route('admin.catalog.create','movies') }}">+ Add a movie</a></div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach([['Movies','movies','text-white'],['Series','series','text-white'],['Episodes','episodes','text-white'],['Members','members','text-white']] as $item)
<div class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><p class="text-sm text-zinc-400">{{ $item[0] }}</p><p class="mt-3 text-4xl font-black tabular-nums">{{ number_format($metrics[$item[1]]) }}</p></div>
@endforeach
</div>
<div class="mt-6 grid gap-4 md:grid-cols-3">
<div class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><p class="text-sm text-zinc-400">Published movies</p><p class="mt-3 text-3xl font-bold text-[#F5C518]">{{ $metrics['published_movies'] }}</p></div>
<div class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><p class="text-sm text-zinc-400">Published series</p><p class="mt-3 text-3xl font-bold text-[#F5C518]">{{ $metrics['published_series'] }}</p></div>
<a href="{{ route('admin.media.index') }}" class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6 hover:border-[#E50914]/40"><p class="text-sm text-zinc-400">Quarantined video uploads</p><p class="mt-3 text-3xl font-bold text-white">{{ $metrics['ingests_pending'] }}</p></a>
</div>
<div class="mt-8 rounded-2xl border border-white/10 bg-zinc-900/60">
  <div class="flex items-center justify-between border-b border-white/10 px-6 py-5"><h3 class="font-semibold">Recently updated movies</h3><a class="text-sm text-[#F5C518]" href="{{ route('admin.catalog.index','movies') }}">Manage all</a></div>
  <div class="overflow-x-auto"><table class="w-full min-w-[580px] text-left text-sm">
    <thead class="text-xs uppercase tracking-wider text-zinc-500"><tr><th class="p-5">Title</th><th class="p-5">Status</th><th class="p-5">Access</th><th class="p-5">Action</th></tr></thead>
    <tbody class="divide-y divide-white/10">@forelse($recentMovies as $movie)<tr><td class="p-5 font-semibold">{{ $movie->title }}</td><td class="p-5 capitalize text-zinc-300">{{ $movie->status }}</td><td class="p-5 text-zinc-400">{{ $movie->is_premium ? 'Premium' : 'Free' }}</td><td class="p-5"><a href="{{ route('admin.catalog.edit',['movies',$movie->id]) }}" class="text-[#F5C518]">Edit</a></td></tr>
    @empty<tr><td colspan="4" class="p-7 text-center text-zinc-500">No movies have been created.</td></tr>@endforelse</tbody>
  </table></div>
</div>
@endsection
