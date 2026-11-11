@extends('layouts.admin')
@section('title','Independent Media Package Review')
@section('content')
<div class="gm-page-heading">
<div><p class="text-xs uppercase tracking-widest text-amber-300">Secure publishing</p><h2>Inspect media package #{{ $ingest->id }}</h2><p>Inspect the full encoded package, license evidence and independent reviewers before public activation.</p></div>
<div class="gm-page-actions"><a href="{{ route('admin.rights-evidence.index') }}" class="gm-studio-button">Return to rights register</a></div>
</div>
<div class="gm-studio-panel p-5">
<h3 class="font-semibold">Verified private HLS package</h3>
<div class="mt-4 grid gap-4 md:grid-cols-2">
<div><div class="gm-studio-muted text-xs">Content</div><strong>{{ ucfirst($ingest->ingestable_type) }} #{{ $ingest->ingestable_id }}</strong></div>
<div><div class="gm-studio-muted text-xs">Current state</div><strong>{{ $ingest->status }}</strong></div>
<div><div class="gm-studio-muted text-xs">Original SHA-256</div><code class="break-all text-xs">{{ $ingest->sha256 }}</code></div>
<div><div class="gm-studio-muted text-xs">Manifest SHA-256</div><code class="break-all text-xs">{{ $package['manifest_sha256'] }}</code></div>
<div class="md:col-span-2"><div class="gm-studio-muted text-xs">Full package SHA-256</div><code class="break-all text-xs">{{ $package['package_sha256'] }}</code></div>
<div><div class="gm-studio-muted text-xs">Verified package files</div><strong>{{ $package['file_count'] }}</strong></div>
<div><div class="gm-studio-muted text-xs">Independent package review</div><strong>{{ $review->status??'Pending' }}</strong></div>
</div>
</div>
<div class="grid gap-5 mt-5 lg:grid-cols-2">
<section class="gm-studio-panel p-5">
<h3 class="font-semibold">1. Independent package verification</h3>
<p class="gm-studio-muted text-sm my-3">Another verified administrator must have independently examined the video, scanning report, rights evidence and quality results. The stored package fingerprint is immutable.</p>
@if($review)
<div class="gm-studio-pill {{ $review->status==='reviewed'?'is-ok':'is-warn' }}">{{ ucfirst($review->status) }} · Reviewer #{{ $review->reviewed_by }}</div>
<p class="gm-studio-muted text-xs mt-3">Rights reference: {{ $review->rights_reference }}</p>
@else
<form method="POST" action="{{ route('admin.rights-evidence.review-package',$ingest->id) }}">@csrf
<label class="gm-studio-field"><span>Approved global rights reference</span><select name="rights_reference" class="gm-studio-input" required><option value="">Select reviewed rights evidence</option>
@foreach($licenses as $license)<option value="{{ $license->rights_reference }}">{{ $license->rights_reference }} · {{ $license->rights_holder }} · expires {{ $license->ends_at }}</option>@endforeach</select></label>
<label class="gm-studio-field"><span>External malware scan report reference</span><input name="scan_reference" class="gm-studio-input" placeholder="SCAN-REPORT-2026-001" minlength="8" maxlength="180" required></label>
<label class="gm-studio-field"><span>External playback and quality review reference</span><input name="quality_reference" class="gm-studio-input" placeholder="QUALITY-REPORT-2026-001" minlength="8" maxlength="180" required></label>
<input type="hidden" name="manifest_sha256" value="{{ $package['manifest_sha256'] }}">
<label class="gm-studio-field"><input type="checkbox" name="package_review_confirmed" value="1" required> <span>I independently reviewed the license, scanning evidence and the full HLS media package fingerprint.</span></label>
<div class="gm-page-actions mt-4"><button class="gm-studio-button is-primary" type="submit">Record package review</button></div>
</form>
@endif
</section>
<section class="gm-studio-panel p-5">
<h3 class="font-semibold">2. Separate publisher activation</h3>
<p class="gm-studio-muted text-sm my-3">Activation requires an approved current license, approved unchanged full package, and a different verified administrator from uploader, ingest approver and package reviewer. Public HLS must also be configured.</p>
@if($review && $review->status==='reviewed')
<form method="POST" action="{{ route('admin.rights-evidence.publish',$ingest->id) }}">@csrf
<input type="hidden" name="expected_package_sha256" value="{{ $package['package_sha256'] }}">
<label class="gm-studio-field"><input type="checkbox" name="publication_confirmed" value="1" required> <span>I independently authorize this exact reviewed package for public playback and accept an auditable publishing record.</span></label>
<div class="gm-page-actions mt-4"><button type="submit" class="gm-studio-button is-primary">Activate reviewed publication</button></div>
</form>
@else
<p class="gm-studio-pill is-warn">Blocked until independent package review</p>
@endif
</section>
</div>
@endsection
