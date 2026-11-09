@extends('layouts.admin')
@section('title','Seasons & Episodes')
@section('content')
<div class="gm-page-heading">
 <div><p class="text-xs uppercase tracking-widest text-amber-300">TV Series Studio</p><h2>{{ $series->title }}</h2><p>Manage seasons, ordered episodes and publication metadata.</p></div>
 <div class="gm-page-actions">
 <a href="{{ route('admin.catalog.edit',['series',$series->id]) }}" class="gm-studio-button">@include('partials.studio-icon',['name'=>'edit']) Edit series</a>
 <button class="gm-studio-button is-primary" type="button" data-studio-open="gm-create-season">@include('partials.studio-icon',['name'=>'plus']) Add season</button>
 </div>
</div>
<div class="gm-studio-metrics mb-6">
 <div class="gm-studio-metric"><small>Seasons</small><strong>{{ $seasons->total() }}</strong></div>
 <div class="gm-studio-metric"><small>Episodes</small><strong>{{ \App\Models\Episode::query()->whereHas('season', fn ($query) => $query->where('series_id', $series->id))->count() }}</strong></div>
 <div class="gm-studio-metric"><small>Published series</small><strong class="text-lg! capitalize">{{ $series->status }}</strong></div>
 <div class="gm-studio-metric"><small>Per table</small><strong class="text-lg!">10 rows</strong></div>
</div>
<dialog class="gm-studio-modal" id="gm-create-season" aria-labelledby="gm-create-season-heading">
<form method="POST" action="{{ route('admin.seasons.store',$series) }}">@csrf
<div class="gm-studio-modal-heading"><h3 id="gm-create-season-heading">Add season</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Season number</span><input type="number" min="0" max="9999" name="season_number" class="gm-studio-input" required></label>
<label class="gm-studio-field"><span>Season title</span><input name="title" maxlength="255" class="gm-studio-input" required placeholder="Season One"></label>
</div><div class="gm-studio-modal-actions"><button class="gm-studio-button" type="button" data-studio-close>Cancel</button><button type="submit" class="gm-studio-button is-primary">Create season</button></div>
</form></dialog>
@forelse($seasons as $season)
@php $episodes=$episodePages[$season->id]; @endphp
<section class="gm-studio-panel mb-6" aria-labelledby="gm-season-heading-{{ $season->id }}">
<div class="gm-studio-panel-head">
 <div><small class="block text-amber-300 uppercase tracking-wider text-xs mb-1">Season {{ $season->season_number }}</small><h3 id="gm-season-heading-{{ $season->id }}">{{ $season->title }}</h3><small>{{ $season->episodes_count }} episodes · 10 items per page</small></div>
 <div class="gm-page-actions">
 <button type="button" class="gm-studio-button" data-studio-open="gm-edit-season-{{ $season->id }}">@include('partials.studio-icon',['name'=>'edit']) Edit season</button>
 <button type="button" class="gm-studio-button is-primary" data-studio-open="gm-create-episode-{{ $season->id }}">@include('partials.studio-icon',['name'=>'plus']) Add episode</button>
 @if($season->episodes_count===0 && in_array(auth()->user()->role,['admin','super_admin'],true))
 <form method="POST" action="{{ route('admin.seasons.destroy',[$series,$season]) }}">@csrf @method('DELETE')
 <button class="gm-studio-button is-danger" type="submit" data-studio-confirm="Delete this empty season?">@include('partials.studio-icon',['name'=>'trash']) Delete empty season</button></form>
 @endif
 </div>
</div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table">
<thead><tr><th>No.</th><th>Episode</th><th>Runtime</th><th>Status</th><th>Air date</th><th class="text-right">Actions</th></tr></thead>
<tbody>
@forelse($episodes as $episode)
<tr>
<td>{{ ($episodes->firstItem() ?? 1)+$loop->index }}</td>
<td><span class="gm-studio-muted">#{{ $episode->episode_number }}</span> <strong>{{ $episode->title }}</strong></td>
<td class="gm-studio-muted">{{ $episode->runtime_minutes ? $episode->runtime_minutes.' minutes' : '—' }}</td>
<td><span class="gm-studio-pill {{ $episode->status==='published'?'is-ok':'is-warn' }}">{{ ucfirst($episode->status) }}</span></td>
<td class="gm-studio-muted">{{ $episode->air_date?->format('Y-m-d') ?? '—' }}</td>
<td class="text-right"><button type="button" class="gm-studio-dots" aria-label="Actions for {{ $episode->title }}" data-studio-menu="gm-episode-actions-{{ $episode->id }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="gm-episode-actions-{{ $episode->id }}">
<button role="menuitem" type="button" data-studio-open="gm-edit-episode-{{ $episode->id }}">@include('partials.studio-icon',['name'=>'edit']) Edit episode</button>
@if($episode->status==='draft' && in_array(auth()->user()->role,['admin','super_admin'],true))
<form method="POST" action="{{ route('admin.episodes.destroy',[$series,$season,$episode]) }}">@csrf @method('DELETE')
<button role="menuitem" type="submit" class="is-danger" data-studio-confirm="Delete this draft episode?">@include('partials.studio-icon',['name'=>'trash']) Delete draft</button></form>
@endif
</template></td>
</tr>
@empty<tr><td colspan="6" class="gm-studio-empty">No episodes in this season. Choose Add episode to create a draft.</td></tr>
@endforelse
</tbody>
</table></div>
@include('partials.studio-pager',['paginator'=>$episodes,'id'=>'gm-episode-page-'.$season->id])
</section>
<dialog class="gm-studio-modal" id="gm-edit-season-{{ $season->id }}" aria-labelledby="gm-edit-season-heading-{{ $season->id }}">
<form method="POST" action="{{ route('admin.seasons.update',[$series,$season]) }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-edit-season-heading-{{ $season->id }}">Edit {{ $season->title }}</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Season number</span><input class="gm-studio-input" type="number" name="season_number" min="0" max="9999" value="{{ $season->season_number }}" required></label>
<label class="gm-studio-field"><span>Season title</span><input class="gm-studio-input" name="title" maxlength="255" value="{{ $season->title }}" required></label>
</div><div class="gm-studio-modal-actions"><button class="gm-studio-button" type="button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary" type="submit">Save season</button></div>
</form></dialog>
<dialog class="gm-studio-modal" id="gm-create-episode-{{ $season->id }}" aria-labelledby="gm-create-episode-heading-{{ $season->id }}">
<form method="POST" action="{{ route('admin.episodes.store',[$series,$season]) }}">@csrf
<div class="gm-studio-modal-heading"><h3 id="gm-create-episode-heading-{{ $season->id }}">Add episode · Season {{ $season->season_number }}</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Episode number</span><input class="gm-studio-input" type="number" name="episode_number" min="1" max="10000" required></label>
<label class="gm-studio-field"><span>Title</span><input class="gm-studio-input" name="title" maxlength="255" required></label>
<label class="gm-studio-field"><span>Synopsis</span><textarea class="gm-studio-input" name="overview" rows="3" maxlength="12000"></textarea></label>
<label class="gm-studio-field"><span>Runtime in minutes</span><input class="gm-studio-input" type="number" name="runtime_minutes" min="1" max="1440"></label>
<label class="gm-studio-field"><span>Air date</span><input class="gm-studio-input" type="date" name="air_date"></label>
<label class="gm-studio-field"><span>Status</span><select class="gm-studio-input" name="status"><option value="draft" selected>Draft</option><option value="published" @disabled($series->status!=='published')>Published</option></select></label>
<p class="gm-studio-muted text-xs">Publishing metadata never enables a private or unlicensed video stream.</p>
</div><div class="gm-studio-modal-actions"><button class="gm-studio-button" type="button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary" type="submit">Create episode</button></div>
</form></dialog>
@foreach($episodes as $episode)
<dialog class="gm-studio-modal" id="gm-edit-episode-{{ $episode->id }}" aria-labelledby="gm-edit-episode-heading-{{ $episode->id }}">
<form method="POST" action="{{ route('admin.episodes.update',[$series,$season,$episode]) }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-edit-episode-heading-{{ $episode->id }}">Edit {{ $episode->title }}</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Episode number</span><input class="gm-studio-input" type="number" name="episode_number" min="1" max="10000" value="{{ $episode->episode_number }}" required></label>
<label class="gm-studio-field"><span>Title</span><input class="gm-studio-input" name="title" maxlength="255" value="{{ $episode->title }}" required></label>
<label class="gm-studio-field"><span>Synopsis</span><textarea class="gm-studio-input" name="overview" rows="3" maxlength="12000">{{ $episode->overview }}</textarea></label>
<label class="gm-studio-field"><span>Runtime minutes</span><input class="gm-studio-input" name="runtime_minutes" type="number" min="1" max="1440" value="{{ $episode->runtime_minutes }}"></label>
<label class="gm-studio-field"><span>Air date</span><input class="gm-studio-input" type="date" name="air_date" value="{{ $episode->air_date?->format('Y-m-d') }}"></label>
<input type="hidden" name="still_path" value="{{ $episode->still_path }}">
<label class="gm-studio-field"><span>Status</span><select class="gm-studio-input" name="status">@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected($episode->status===$status) @disabled($status==='published' && $series->status!=='published')>{{ ucfirst($status) }}</option>@endforeach</select></label>
</div><div class="gm-studio-modal-actions"><button class="gm-studio-button" type="button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary" type="submit">Save episode</button></div>
</form></dialog>
@endforeach
@empty
<section class="gm-studio-panel"><p class="gm-studio-empty">No seasons yet. Use the Add season button at the top right to start.</p></section>
@endforelse
@include('partials.studio-pager',['paginator'=>$seasons,'id'=>'gm-seasons-page'])
@endsection
