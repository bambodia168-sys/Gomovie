@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="gm-page-heading"><div><p class="text-xs uppercase tracking-widest text-amber-300">Gomovie Studio</p><h2>Content overview</h2><p>Live catalog counts and recent operational activity.</p></div>
<div class="gm-page-actions"><a class="gm-studio-button" href="{{ route('admin.media.index') }}">@include('partials.studio-icon',['name'=>'media']) Media Library</a><a class="gm-studio-button is-primary" href="{{ route('admin.catalog.create','movies') }}">@include('partials.studio-icon',['name'=>'plus']) Add a movie</a></div></div>
<div class="gm-studio-metrics">
@foreach([['Movies','movies'],['Series','series'],['Episodes','episodes'],['Members','members']] as $metric)
<div class="gm-studio-metric"><small>{{ $metric[0] }}</small><strong>{{ number_format($metrics[$metric[1]]) }}</strong></div>
@endforeach
</div>
<div class="grid gap-4 mt-5 md:grid-cols-3">
<div class="gm-studio-metric"><small>Published movies</small><strong class="text-amber-300">{{ $metrics['published_movies'] }}</strong></div>
<div class="gm-studio-metric"><small>Published series</small><strong class="text-amber-300">{{ $metrics['published_series'] }}</strong></div>
<a href="{{ route('admin.media.index') }}" class="gm-studio-metric"><small>Private video uploads</small><strong>{{ $metrics['ingests_pending'] }}</strong></a>
</div>
<section class="gm-studio-panel mt-6">
<div class="gm-studio-panel-head"><div><h3>Recently updated movies</h3><small>Latest catalog records · 10 per page</small></div><a class="gm-studio-button" href="{{ route('admin.catalog.index','movies') }}">Manage all</a></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table">
<thead><tr><th>No.</th><th>Title</th><th>Status</th><th>Access</th><th class="text-right">Actions</th></tr></thead>
<tbody>@forelse($recentMovies as $movie)
<tr><td>{{ ($recentMovies->firstItem() ?? 1)+$loop->index }}</td><td><a href="{{ route('admin.catalog.edit',['movies',$movie->id]) }}" class="hover:text-rose-300">{{ $movie->title }}</a></td>
<td><span class="gm-studio-pill {{ $movie->status==='published'?'is-ok':'is-warn' }}">{{ ucfirst($movie->status) }}</span></td><td>{{ $movie->is_premium?'Premium':'Free' }}</td><td class="text-right">
<button type="button" class="gm-studio-dots" data-studio-menu="recent-{{ $movie->id }}" aria-label="Actions for {{ $movie->title }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="recent-{{ $movie->id }}"><a role="menuitem" href="{{ route('admin.catalog.edit',['movies',$movie->id]) }}">@include('partials.studio-icon',['name'=>'edit']) Edit movie</a><a role="menuitem" href="{{ route('admin.catalog.preview',['movies',$movie->id]) }}">@include('partials.studio-icon',['name'=>'eye']) Preview</a></template>
</td></tr>@empty<tr><td colspan="5" class="gm-studio-empty">No movies have been created.</td></tr>@endforelse</tbody>
</table></div>
@include('partials.studio-pager',['paginator'=>$recentMovies,'id'=>'dashboard-go'])
</section>
@endsection
