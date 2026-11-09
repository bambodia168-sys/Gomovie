@extends('layouts.admin')
@section('title','Media Library')
@section('content')
<div class="gm-page-heading">
<div><p class="text-xs uppercase tracking-widest text-amber-300">Content management</p><h2>Media Library</h2><p>Upload original videos privately or publish free videos automatically when processing and rights checks pass.</p></div>
<div class="gm-page-actions">
<button class="gm-studio-button" type="button" data-studio-open="gm-artwork-upload-dialog">@include('partials.studio-icon',['name'=>'media']) Upload artwork</button>
<button class="gm-studio-button" type="button" data-studio-open="gm-tmdb-dialog">@include('partials.studio-icon',['name'=>'plus']) Import metadata</button>
@if(in_array(auth()->user()->role,['admin','super_admin'],true))
<a class="gm-studio-button is-primary" href="{{ route('upload-studio.index') }}">@include('partials.studio-icon',['name'=>'upload']) Upload video</a>
@endif</div></div>
<section class="gm-studio-panel">
<div class="gm-studio-panel-head"><div><h3>Uploaded videos</h3><small>Current private and processed video assets · 10 per page</small></div>
<span class="gm-studio-pill">{{ number_format($assets->total()) }} total</span></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table"><thead><tr><th>No.</th><th>Video file</th><th>Uploader</th><th>Size</th><th>Status</th><th>Uploaded</th><th class="text-right">Actions</th></tr></thead><tbody>
@forelse($assets as $asset)
<tr><td>{{ ($assets->firstItem()??1)+$loop->index }}</td><td class="break-words">{{ $asset->original_name }}</td>
<td class="gm-studio-muted">{{ $asset->uploader }}</td><td class="gm-studio-muted">{{ number_format($asset->size_bytes / 1048576, 1) }} MB</td>
<td><span class="gm-studio-pill {{ $asset->status==='quarantined'?'is-warn':'is-ok' }}">{{ $asset->public?'Public and playable':($asset->status==='quarantined'?'Saved privately':ucfirst($asset->status)) }}</span></td>
<td class="gm-studio-muted">{{ $asset->created_at }}</td>
<td class="text-right"><button class="gm-studio-dots" type="button" aria-label="Actions for {{ $asset->original_name }}" data-studio-menu="asset-actions-{{ $asset->id }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="asset-actions-{{ $asset->id }}">
@if(in_array(auth()->user()->role,['admin','super_admin'],true))
<a role="menuitem" href="{{ route('admin.media.ingest-preview',$asset->id) }}" target="_blank" rel="noopener noreferrer">@include('partials.studio-icon',['name'=>'eye']) Private preview</a>
@if($asset->public && ($asset->watch_url ?? null))
<a role="menuitem" href="{{ $asset->watch_url }}" target="_blank" rel="noopener noreferrer">@include('partials.studio-icon',['name'=>'eye']) Watch public video</a>
@endif
@endif
@if((int)$asset->uploaded_by===(int)auth()->id() && $asset->status==='quarantined')
<a role="menuitem" href="{{ route('upload-studio.index',['upload'=>$asset->id]) }}">@include('partials.studio-icon',['name'=>'edit']) Edit upload</a>
@endif
</template></td></tr>
@empty<tr><td colspan="7" class="gm-studio-empty">No uploaded videos yet. Use Upload Studio to add your first licensed video.</td></tr>@endforelse
</tbody></table></div>
@include('partials.studio-pager',['paginator'=>$assets,'id'=>'media-page-go'])
</section>
<div class="grid gap-4 mt-5 lg:grid-cols-2">
<div class="gm-studio-panel p-5"><h3 class="font-bold text-sm">Artwork management</h3><p class="gm-studio-muted text-sm mt-2">Upload a licensed poster or backdrop linked to an existing movie or series. Media files are validated on the server.</p></div>
<div class="gm-studio-panel p-5"><h3 class="font-bold text-sm">Draft metadata import</h3><p class="gm-studio-muted text-sm mt-2">Import catalog metadata using a configured TMDB API token. Metadata is not proof of distribution rights.</p></div>
</div>
<dialog class="gm-studio-modal" id="gm-artwork-upload-dialog" aria-labelledby="gm-artwork-heading">
<form method="POST" action="{{ route('admin.media.artwork') }}" enctype="multipart/form-data">@csrf
<div class="gm-studio-modal-heading"><h3 id="gm-artwork-heading">Upload artwork</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Content type</span><select name="type" class="gm-studio-input" required><option value="movie">Movie</option><option value="series">Series</option></select></label>
<label class="gm-studio-field"><span>Existing title ID</span><input class="gm-studio-input" name="title_id" type="number" min="1" required></label>
<label class="gm-studio-field"><span>Image placement</span><select name="slot" class="gm-studio-input"><option value="poster">Poster</option><option value="backdrop">Backdrop</option></select></label>
<label class="gm-studio-field"><span>JPG, PNG or WebP · maximum 8 MB</span><input class="gm-studio-input" type="file" accept="image/jpeg,image/png,image/webp" name="image" required></label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary" type="submit">Upload artwork</button></div></form></dialog>
<dialog class="gm-studio-modal" id="gm-tmdb-dialog" aria-labelledby="gm-tmdb-heading">
<form method="POST" action="{{ route('admin.tmdb.import') }}">@csrf
<div class="gm-studio-modal-heading"><h3 id="gm-tmdb-heading">Import TMDB metadata</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body"><p class="gm-studio-muted text-sm mb-5">Imported titles remain drafts until approved. Metadata does not grant video rights.</p>
<label class="gm-studio-field"><span>Content type</span><select class="gm-studio-input" name="type"><option value="movie">Movie</option><option value="series">Series</option></select></label>
<label class="gm-studio-field"><span>TMDB ID</span><input class="gm-studio-input" name="tmdb_id" type="number" min="1" required></label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button type="submit" class="gm-studio-button is-primary">Import as draft</button></div></form></dialog>
@endsection
