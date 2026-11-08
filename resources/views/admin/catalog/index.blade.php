@extends('layouts.admin')
@section('title', $type === 'movies' ? 'Movies' : 'TV Series')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-3xl font-bold">Manage {{ $type === 'movies' ? 'Movies' : 'Series' }}</h2><p class="mt-2 text-sm text-zinc-400">Create, review and publish licensed catalog metadata.</p></div>
<a href="{{ route('admin.catalog.create',$type) }}" class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold hover:bg-red-700">+ New title</a></div>
<form method="GET" class="mb-6 flex flex-wrap gap-3">
 <label for="query" class="sr-only">Search titles</label><input id="query" name="q" value="{{ request('q') }}" placeholder="Search title" maxlength="100" class="min-w-52 flex-1 rounded-xl border border-white/15 bg-zinc-900 px-4 py-3 text-sm outline-none focus:border-[#E50914]">
 <label for="status" class="sr-only">Filter status</label><select id="status" name="status" class="rounded-xl border border-white/15 bg-zinc-900 px-4 py-3 text-sm"><option value="">All statuses</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
 <button class="rounded-xl border border-white/20 px-5 py-3 text-sm hover:bg-white/5">Apply filters</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-white/10 bg-zinc-900/60"><table class="w-full min-w-[750px] text-left text-sm">
 <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-zinc-500"><tr><th class="p-5">Title</th><th class="p-5">Release</th><th class="p-5">Access</th><th class="p-5">Status</th><th class="p-5 text-right">Action</th></tr></thead>
 <tbody class="divide-y divide-white/10">@forelse($titles as $title)<tr class="hover:bg-white/[.025]">
   <td class="p-5"><div class="font-semibold">{{ $title->title }}</div><div class="mt-1 text-xs text-zinc-500">{{ $title->slug }}</div></td>
   <td class="p-5 text-zinc-400">{{ $type === 'movies' ? ($title->release_year ?? '—') : (optional($title->first_air_date)->format('Y') ?? '—') }}</td>
   <td class="p-5 text-zinc-400">{{ $title->is_premium ? 'Premium' : 'Free' }}</td>
   <td class="p-5"><span class="rounded-full border border-white/15 px-3 py-1 text-xs capitalize">{{ $title->status }}</span></td>
   <td class="p-5 text-right"><a href="{{ route('admin.catalog.edit',[$type,$title->id]) }}" class="text-[#F5C518] hover:underline">Edit / Preview</a></td>
 </tr>@empty<tr><td colspan="5" class="p-8 text-center text-zinc-500">No matching titles. Create your first entry.</td></tr>@endforelse</tbody>
</table></div><div class="mt-6">{{ $titles->links() }}</div>
@endsection
