@extends('layouts.account')
@section('title',$section==='watchlist'?'My List':'Watch history')
@section('account_content')
<div class="gm-page-heading">
  <div><p class="text-xs uppercase tracking-widest text-rose-300">Personal library</p><h1>{{ $section==='watchlist'?'My List':'Watch history' }}</h1><p>Private titles and viewing progress for the selected profile.</p></div>
  <div class="gm-page-actions">
    <a class="gm-studio-button" href="{{ route('browse') }}">@include('partials.studio-icon',['name'=>'external']) Explore titles</a>
    @if($records->total()>0)
    <button type="button" class="gm-studio-button is-danger" data-studio-open="account-clear-dialog">
      @include('partials.studio-icon',['name'=>'trash']) {{ $section==='watchlist'?'Clear My List':'Clear watch history' }}
    </button>
    @endif
  </div>
</div>
<div class="gm-studio-subnav">
<a href="{{ route('account.library',['section'=>'watchlist','profile_id'=>$profile->id]) }}" @class(['is-current'=>$section==='watchlist'])>My List</a>
<a href="{{ route('account.library',['section'=>'history','profile_id'=>$profile->id]) }}" @class(['is-current'=>$section==='history'])>Watch history</a>
</div>
<form method="GET" action="{{ route('account.library',['section'=>$section]) }}" class="gm-studio-panel mb-5 p-4 flex flex-wrap gap-3 items-end">
 <label class="gm-studio-field mb-0!"><span>Viewer profile</span><select class="gm-studio-input" name="profile_id">@foreach($profiles as $p)<option value="{{ $p->id }}" @selected($profile->id===$p->id)>{{ $p->name }}</option>@endforeach</select></label>
 <button class="gm-studio-button" type="submit">Apply profile</button>
</form>
<section class="gm-studio-panel">
<div class="gm-studio-panel-head"><div><h3>{{ $section==='watchlist'?'Saved titles':'Viewing activity' }}</h3><small>10 items per page</small></div><span class="gm-studio-pill">{{ $records->total() }} records</span></div>
<div class="gm-studio-table-scroll">
<table class="gm-studio-table"><thead><tr><th>No.</th><th>Title</th><th>Type</th><th>{{ $section==='history'?'Progress':'Saved' }}</th><th class="text-right">Actions</th></tr></thead>
<tbody>
@forelse($entries as $entry)
@php
  $item=$entry['item'];$kind=$entry['kind'];$row=$entry['row'];
  $parent=$kind==='episode'?$item->season?->series:null;
  $link=$parent?route('watch.show',['series',$parent->slug]):route('watch.show',[$kind,$item->slug]);
  $progress=$section==='history'&&$row->duration_seconds>0?min(100,round(100*$row->progress_seconds/$row->duration_seconds)):0;
@endphp
<tr><td>{{ ($records->firstItem() ?? 1)+$loop->index }}</td><td><a href="{{ $link }}" class="font-semibold hover:text-rose-300">{{ $item->title }}</a><small class="block gm-studio-muted mt-1">{{ $parent?->title }}</small></td>
<td class="capitalize gm-studio-muted">{{ $kind }}</td>
<td>@if($section==='history')<span class="gm-studio-pill">{{ $progress }}% watched</span>@else<span class="gm-studio-pill is-ok">Saved</span>@endif</td>
<td class="text-right">
 <button type="button" class="gm-studio-dots" aria-label="Actions for {{ $item->title }}" data-studio-menu="account-entry-{{ $row->id }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
 <template id="account-entry-{{ $row->id }}">
   <a role="menuitem" href="{{ $link }}">@include('partials.studio-icon',['name'=>'eye']) View title</a>
   @if($section==='watchlist')
     <form method="POST" action="{{ route('account.watchlist.toggle') }}">@csrf
       <input type="hidden" name="profile_id" value="{{ $profile->id }}"><input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="title_id" value="{{ $item->id }}"><input type="hidden" name="action" value="remove">
       <button type="submit" role="menuitem" class="is-danger" data-studio-confirm="Remove this title from your list?">@include('partials.studio-icon',['name'=>'trash']) Remove from My List</button>
     </form>
   @else
     <form method="POST" action="{{ route('account.history.destroy',$row->id) }}">@csrf @method('DELETE')
       <input type="hidden" name="profile_id" value="{{ $profile->id }}">
       <button type="submit" role="menuitem" class="is-danger" data-studio-confirm="Delete this viewing history entry?">@include('partials.studio-icon',['name'=>'trash']) Delete history</button>
     </form>
   @endif
 </template>
</td></tr>
@empty<tr><td colspan="5" class="gm-studio-empty">No records for this profile. <a class="text-rose-300" href="{{ route('browse') }}">Browse the catalog.</a></td></tr>
@endforelse
</tbody></table>
</div>
@include('partials.studio-pager',['paginator'=>$records,'id'=>'account-library-go'])
</section>
@if($records->total()>0)
<dialog class="gm-studio-modal" id="account-clear-dialog" aria-labelledby="account-clear-dialog-title">
<form method="POST" action="{{ route('account.library.clear',['section'=>$section]) }}">
@csrf @method('DELETE')
<input type="hidden" name="profile_id" value="{{ $profile->id }}">
<div class="gm-studio-modal-heading">
  <h3 id="account-clear-dialog-title">Clear {{ $section==='watchlist'?'My List':'watch history' }}?</h3>
  <button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button>
</div>
<div class="gm-studio-modal-body">
  <p class="text-sm gm-studio-muted">This removes {{ $section==='watchlist'?'all saved titles':'all watch-history entries' }} for <strong>{{ $profile->name }}</strong> only.</p>
  <p class="text-sm gm-studio-muted mt-2">Other profiles, other accounts, catalog titles and media files are not affected.</p>
</div>
<div class="gm-studio-modal-actions">
  <button type="button" class="gm-studio-button" data-studio-close>Cancel</button>
  <button type="submit" class="gm-studio-button is-danger">Confirm clear</button>
</div>
</form>
</dialog>
@endif
@endsection
