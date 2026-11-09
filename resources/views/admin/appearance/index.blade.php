@extends('layouts.admin')
@section('title','Appearance')
@section('content')
<div class="gm-appearance-workspace">
<div class="gm-page-heading"><div><p class="text-xs uppercase tracking-widest text-amber-300">Branding & frontend pages</p>
<h2>Appearance</h2><p>Customize text, metadata and typography without rearranging frontend layouts.</p></div>
<div class="gm-page-actions">
@if($canEdit)<button class="gm-studio-button" type="button" data-studio-open="appearance-type">@include('partials.studio-icon',['name'=>'type']) Typography</button>
<button class="gm-studio-button is-primary" type="button" data-studio-open="appearance-new">@include('partials.studio-icon',['name'=>'plus']) Create page</button>@endif
</div></div>
<div class="gm-studio-metrics mb-5">
 <div class="gm-studio-metric"><small>Existing frontend pages</small><strong>{{ count($builtIns) }}</strong></div>
 <div class="gm-studio-metric"><small>Custom pages</small><strong>{{ $customPages->total() }}</strong></div>
 <div class="gm-studio-metric"><small>Edited built-in pages</small><strong>{{ $overrides->count() }}</strong></div>
 <div class="gm-studio-metric"><small>Typography</small><strong class="text-base! truncate">{{ $typography->family }}</strong></div>
</div>
<section class="gm-studio-panel mb-6">
<div class="gm-studio-panel-head"><div><h3>Frontend page templates</h3><small>Edit the wording of existing pages; the actual page structure stays unchanged.</small></div><span class="gm-studio-pill">{{ count($builtIns) }} pages</span></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table">
<thead><tr><th>No.</th><th>Frontend page</th><th>URL</th><th>Customization</th><th class="text-right">Actions</th></tr></thead><tbody>
@foreach($builtIns as $key=>$page)
@php $row=$overrides->get($key);$url=$page['route']?route($page['route']):null; @endphp
<tr><td>{{ $loop->iteration }}</td><td>{{ $page['label'] }}</td><td class="gm-studio-muted">{{ $url?parse_url($url,PHP_URL_PATH):'Dynamic title route' }}</td>
<td><span class="gm-studio-pill {{ $row?'is-ok':'is-warn' }}">{{ $row?'Customized':'Default' }}</span></td>
<td class="text-right"><button type="button" class="gm-studio-dots" data-studio-menu="appearance-menu-{{ $key }}" aria-expanded="false" aria-label="Actions for {{ $page['label'] }}">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="appearance-menu-{{ $key }}">
@if($url)<a role="menuitem" href="{{ $url }}" target="_blank" rel="noopener noreferrer">@include('partials.studio-icon',['name'=>'eye']) View page</a>@endif
@if($canEdit)
<button type="button" role="menuitem" data-studio-open="appearance-edit-{{ $key }}">@include('partials.studio-icon',['name'=>'edit']) Edit content</button>
@if($row)<form method="POST" action="{{ route('admin.appearance.pages.destroy',$key) }}">@csrf @method('DELETE')
<button type="submit" role="menuitem" class="is-danger" data-studio-confirm="Reset this page to its original content?">@include('partials.studio-icon',['name'=>'trash']) Reset page</button></form>@endif
@endif
</template></td></tr>
@endforeach
</tbody></table></div>
</section>
<section class="gm-studio-panel">
<div class="gm-studio-panel-head"><div><h3>Custom frontend pages</h3><small>New pages use the existing frontend header/footer. Draft pages are not publicly accessible.</small></div>
<form method="GET" action="{{ route('admin.appearance.index') }}" class="gm-page-actions">
<label class="sr-only" for="page-search">Search custom pages</label><input class="gm-studio-input" id="page-search" name="q" value="{{ request('q') }}" maxlength="80" placeholder="Search pages">
<button class="gm-studio-button" type="submit">Search</button>
</form></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table"><thead><tr><th>No.</th><th>Page</th><th>Path</th><th>Visibility</th><th class="text-right">Actions</th></tr></thead><tbody>
@forelse($customPages as $page)
<tr><td>{{ ($customPages->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $page->heading }}</strong><small class="block gm-studio-muted">{{ $page->slug }}</small></td>
<td class="gm-studio-muted">/pages/{{ $page->slug }}</td><td><span class="gm-studio-pill {{ $page->is_published?'is-ok':'is-warn' }}">{{ $page->is_published?'Published':'Draft' }}</span></td>
<td class="text-right"><button class="gm-studio-dots" type="button" data-studio-menu="appearance-custom-{{ $page->id }}" aria-label="Actions for {{ $page->slug }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="appearance-custom-{{ $page->id }}">
@if($page->is_published)<a href="{{ route('appearance.page',$page->slug) }}" target="_blank" rel="noopener noreferrer" role="menuitem">@include('partials.studio-icon',['name'=>'eye']) View published page</a>@endif
@if($canEdit)
<button type="button" role="menuitem" data-studio-open="appearance-edit-custom-{{ $page->id }}">@include('partials.studio-icon',['name'=>'edit']) Edit page</button>
<form method="POST" action="{{ route('admin.appearance.pages.destroy',$page->slug) }}">@csrf @method('DELETE')<button type="submit" role="menuitem" class="is-danger" data-studio-confirm="Permanently delete this custom page?">@include('partials.studio-icon',['name'=>'trash']) Delete page</button></form>
@endif
</template></td></tr>
@empty<tr><td colspan="5" class="gm-studio-empty">No custom pages. Create a page using the top-right button.</td></tr>@endforelse</tbody></table></div>
@include('partials.studio-pager',['paginator'=>$customPages,'id'=>'appearance-page-go'])
</section>
@if($canEdit)
<dialog class="gm-studio-modal" id="appearance-new" aria-labelledby="appearance-new-heading">
<form method="POST" action="{{ route('admin.appearance.pages.store') }}">@csrf
<div class="gm-studio-modal-heading"><h3 id="appearance-new-heading">Create frontend page</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>URL slug</span><input class="gm-studio-input" name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" required maxlength="90" placeholder="our-story"><small class="gm-studio-muted">Published at /pages/your-slug</small></label>
<label class="gm-studio-field"><span>Page heading</span><input class="gm-studio-input" name="heading" required maxlength="180"></label>
<label class="gm-studio-field"><span>Eyebrow</span><input class="gm-studio-input" name="eyebrow" maxlength="120"></label>
<label class="gm-studio-field"><span>Description</span><textarea class="gm-studio-input" name="description" rows="3" maxlength="2000"></textarea></label>
<label class="gm-studio-field"><span>Main content (plain text)</span><textarea class="gm-studio-input" name="body" rows="5" maxlength="16000"></textarea></label>
<label class="gm-studio-field"><span>SEO title</span><input class="gm-studio-input" name="seo_title" maxlength="160"></label>
<label class="gm-studio-field"><span>SEO description</span><textarea class="gm-studio-input" name="seo_description" rows="2" maxlength="300"></textarea></label>
<label class="flex items-center gap-2 text-sm"><input class="gm-studio-input" type="checkbox" name="is_published" value="1"> Publish this page</label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Create page</button></div></form></dialog>
@foreach($builtIns as $key=>$defaults)
@php $page=$overrides->get($key); @endphp
<dialog class="gm-studio-modal" id="appearance-edit-{{ $key }}" aria-labelledby="appearance-edit-heading-{{ $key }}">
<form method="POST" action="{{ route('admin.appearance.pages.update',$key) }}">@csrf @method('PUT')
<div class="gm-studio-modal-heading"><h3 id="appearance-edit-heading-{{ $key }}">Edit {{ $defaults['label'] }}</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<p class="text-xs gm-studio-muted mb-5">Layout and catalog behavior are unchanged. Only editable copy and SEO fields are saved.</p>
<label class="gm-studio-field"><span>Eyebrow / small heading</span><input class="gm-studio-input" name="eyebrow" maxlength="120" value="{{ $page?->eyebrow ?? $defaults['eyebrow'] }}"></label>
<label class="gm-studio-field"><span>Main heading</span><input class="gm-studio-input" name="heading" maxlength="180" value="{{ $page?->heading ?? $defaults['heading'] }}"></label>
<label class="gm-studio-field"><span>Introduction</span><textarea class="gm-studio-input" name="description" rows="3" maxlength="2000">{{ $page?->description ?? $defaults['description'] }}</textarea></label>
<label class="gm-studio-field"><span>SEO title</span><input class="gm-studio-input" name="seo_title" maxlength="160" value="{{ $page?->seo_title }}"></label>
<label class="gm-studio-field"><span>SEO description</span><textarea class="gm-studio-input" name="seo_description" rows="2" maxlength="300">{{ $page?->seo_description }}</textarea></label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Save changes</button></div></form></dialog>
@endforeach
@foreach($customPages as $page)
<dialog class="gm-studio-modal" id="appearance-edit-custom-{{ $page->id }}" aria-labelledby="appearance-custom-heading-{{ $page->id }}">
<form method="POST" action="{{ route('admin.appearance.pages.update',$page->slug) }}">@csrf @method('PUT')
<div class="gm-studio-modal-heading"><h3 id="appearance-custom-heading-{{ $page->id }}">Edit /pages/{{ $page->slug }}</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Heading</span><input name="heading" value="{{ $page->heading }}" class="gm-studio-input" required maxlength="180"></label>
<label class="gm-studio-field"><span>Eyebrow</span><input name="eyebrow" class="gm-studio-input" value="{{ $page->eyebrow }}" maxlength="120"></label>
<label class="gm-studio-field"><span>Description</span><textarea class="gm-studio-input" name="description" rows="3" maxlength="2000">{{ $page->description }}</textarea></label>
<label class="gm-studio-field"><span>Body (plain text)</span><textarea class="gm-studio-input" name="body" rows="6" maxlength="16000">{{ $page->body }}</textarea></label>
<label class="gm-studio-field"><span>SEO title</span><input name="seo_title" class="gm-studio-input" value="{{ $page->seo_title }}" maxlength="160"></label>
<label class="gm-studio-field"><span>SEO description</span><textarea name="seo_description" class="gm-studio-input" rows="2" maxlength="300">{{ $page->seo_description }}</textarea></label>
<label class="flex items-center gap-2 text-sm"><input class="gm-studio-input" name="is_published" value="1" type="checkbox" @checked($page->is_published)> Published</label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Save page</button></div></form></dialog>
@endforeach
<dialog class="gm-studio-modal" id="appearance-type" aria-labelledby="appearance-type-heading">
<form method="POST" action="{{ route('admin.appearance.typography.update') }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="appearance-type-heading">Typography settings</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Font family</span><select class="gm-studio-input" name="family">@foreach(['Instrument Sans','Inter','Arial','Georgia','System UI','Custom'] as $family)<option value="{{ $family }}" @selected($typography->family===$family)>{{ $family }}</option>@endforeach</select></label>
<label class="gm-studio-field"><span>Base font size</span><select name="base_size" class="gm-studio-input">@foreach(range(14,20) as $size)<option value="{{ $size }}" @selected((int)$typography->base_size===$size)>{{ $size }}px</option>@endforeach</select></label>
<label class="gm-studio-field"><span>Heading scale</span><select name="heading_scale" class="gm-studio-input">@foreach([90,95,100,105,110,115,120] as $scale)<option value="{{ $scale }}" @selected((int)$typography->heading_scale===$scale)>{{ $scale }}%</option>@endforeach</select></label>
<label class="gm-studio-field"><span>Heading weight / variant</span><select name="weight" class="gm-studio-input">@foreach([400,500,600,700,800,900] as $weight)<option value="{{ $weight }}" @selected((int)$typography->weight===$weight)>{{ $weight }}</option>@endforeach</select></label>
<p class="text-xs gm-studio-muted">Font choices use installed browser/system families. Custom loads only after a validated WOFF2 upload.</p>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Save typography</button></div></form>
<form method="POST" action="{{ route('admin.appearance.font.store') }}" enctype="multipart/form-data" class="border-t border-white/15 px-5 py-5">@csrf
<label class="gm-studio-field"><span>Upload custom font (.woff2, max 1 MB)</span><input type="file" class="gm-studio-input" name="font" accept=".woff2,font/woff2" required></label>
<div class="flex justify-end"><button class="gm-studio-button" type="submit">@include('partials.studio-icon',['name'=>'upload']) Upload font</button></div></form>
</dialog>
@endif
</div>
@endsection
