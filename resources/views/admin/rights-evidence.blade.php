@extends('layouts.admin')
@section('title','Rights & Publication')
@section('content')
<div class="gm-page-heading">
  <div>
    <p class="text-xs uppercase tracking-widest text-amber-300">Protected media operations</p>
    <h2>Rights & Publication</h2>
    <p>Keep legal evidence private, require independent verification, and approve publication only after a separate media package review.</p>
  </div>
  <div class="gm-page-actions">
    <button type="button" class="gm-studio-button is-primary" data-studio-open="rights-evidence-create">
      @include('partials.studio-icon',['name'=>'plus']) Add license evidence
    </button>
    <a class="gm-studio-button" href="{{ route('admin.media.index') }}">
      @include('partials.studio-icon',['name'=>'media']) Media Library
    </a>
  </div>
</div>

<section class="gm-studio-panel">
  <div class="gm-studio-panel-head">
    <div><h3>License evidence register</h3><small>Scanned private documents · independent operator attestation · expiration and revocation</small></div>
    <span class="gm-studio-pill">{{ number_format($evidence->total()) }} records</span>
  </div>
  <div class="gm-studio-table-scroll">
    <table class="gm-studio-table">
      <thead><tr><th>No.</th><th>Content</th><th>License reference / holder</th><th>Valid until</th><th>Submitted / reviewer</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      @forelse($evidence as $item)
      @php
        $expired = \Illuminate\Support\Carbon::parse($item->ends_at)->isPast();
        $isActive = $item->status === 'approved' && !$expired;
      @endphp
      <tr>
        <td>{{ ($evidence->firstItem() ?? 1) + $loop->index }}</td>
        <td>{{ ucfirst($item->licensable_type) }} #{{ $item->licensable_id }}</td>
        <td><strong>{{ $item->rights_reference }}</strong><div class="gm-studio-muted text-xs">{{ $item->rights_holder }}</div></td>
        <td class="gm-studio-muted">{{ $item->ends_at }}</td>
        <td><span>{{ $item->submitter_name }}</span><div class="gm-studio-muted text-xs">{{ $item->reviewer_name ?? 'Independent review pending' }}</div></td>
        <td><span class="gm-studio-pill {{ $isActive?'is-ok':'is-warn' }}">{{ $expired && $item->status==='approved'?'Expired':ucfirst($item->status) }}</span></td>
        <td class="text-right">
          <button type="button" class="gm-studio-dots" data-studio-menu="rights-evidence-actions-{{ $item->id }}" aria-label="License actions for {{ $item->rights_reference }}" aria-expanded="false">
            @include('partials.studio-icon',['name'=>'dots'])
          </button>
          <template id="rights-evidence-actions-{{ $item->id }}">
            @if($item->status !== 'revoked')
            <a role="menuitem" href="{{ route('admin.rights-evidence.document',$item->id) }}" download>
              @include('partials.studio-icon',['name'=>'eye']) Download private evidence
            </a>
            @endif
            @if($item->status==='pending' && (int)$item->submitted_by !== (int)auth()->id())
            <button type="button" role="menuitem" data-studio-open="rights-evidence-verify-{{ $item->id }}">Verify license independently</button>
            @endif
            @if($item->status !== 'revoked')
            <button type="button" role="menuitem" data-studio-open="rights-evidence-revoke-{{ $item->id }}">Revoke this license</button>
            @endif
          </template>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="gm-studio-empty">No verified license evidence has been submitted. Upload a legal document; do not treat a creator's declaration as distribution permission.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.studio-pager',['paginator'=>$evidence,'id'=>'rights-evidence-go'])
</section>

<section class="gm-studio-panel mt-5">
  <div class="gm-studio-panel-head"><div><h3>Encoded media awaiting review</h3><small>Inspect original and package fingerprints before independent publication.</small></div></div>
  <div class="gm-studio-table-scroll"><table class="gm-studio-table">
    <thead><tr><th>Ingest</th><th>Content</th><th>Media state</th><th>Package review</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
      @forelse($encoded as $asset)
      <tr><td>#{{ $asset->id }}</td><td>{{ ucfirst($asset->ingestable_type) }} #{{ $asset->ingestable_id }}</td><td>{{ ucfirst($asset->status) }}</td>
        <td><span class="gm-studio-pill {{ $asset->review_status==='reviewed'?'is-ok':'is-warn' }}">{{ $asset->review_status??'Awaiting independent review' }}</span></td>
        <td class="text-right"><a class="gm-studio-button" href="{{ route('admin.rights-evidence.inspect', $asset->id) }}">Inspect and review</a></td>
      </tr>
      @empty<tr><td colspan="5" class="gm-studio-empty">No encoded media packages are awaiting independent inspection.</td></tr>@endforelse
    </tbody></table></div>
</section>

<dialog class="gm-studio-modal" id="rights-evidence-create" aria-labelledby="rights-evidence-create-heading">
  <form method="POST" enctype="multipart/form-data" action="{{ route('admin.rights-evidence.submit') }}">@csrf
    <div class="gm-studio-modal-heading"><h3 id="rights-evidence-create-heading">Add license evidence</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
    <div class="gm-studio-modal-body">
      <p class="gm-studio-muted text-sm mb-3">Upload a real rights document. ClamAV must pass before it is stored privately; this submission does not activate public playback.</p>
      <label class="gm-studio-field"><span>Content type</span><select class="gm-studio-input" name="licensable_type" required><option value="movie">Movie</option><option value="episode">Episode</option></select></label>
      <label class="gm-studio-field"><span>Movie or episode ID</span><input class="gm-studio-input" name="licensable_id" type="number" min="1" required></label>
      <label class="gm-studio-field"><span>External license reference</span><input class="gm-studio-input" name="rights_reference" maxlength="180" required placeholder="LICENSE-AGREEMENT-2026-001"></label>
      <label class="gm-studio-field"><span>Legal rights holder</span><input class="gm-studio-input" name="rights_holder" maxlength="180" required></label>
      <label class="gm-studio-field"><span>Worldwide license start</span><input class="gm-studio-input" type="date" name="starts_at" required></label>
      <label class="gm-studio-field"><span>Worldwide license expiry</span><input class="gm-studio-input" type="date" name="ends_at" required></label>
      <label class="gm-studio-field"><span>License document · PDF, JPEG or PNG · maximum 8 MB</span><input class="gm-studio-input" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></label>
    </div>
    <div class="gm-studio-modal-actions"><button type="button" data-studio-close class="gm-studio-button">Cancel</button><button class="gm-studio-button is-primary">Submit privately</button></div>
  </form>
</dialog>

@foreach($evidence as $item)
@if($item->status==='pending' && (int)$item->submitted_by !== (int)auth()->id())
<dialog class="gm-studio-modal" id="rights-evidence-verify-{{ $item->id }}" aria-labelledby="rights-verify-heading-{{ $item->id }}">
<form method="POST" action="{{ route('admin.rights-evidence.verify',$item->id) }}">@csrf
  <div class="gm-studio-modal-heading"><h3 id="rights-verify-heading-{{ $item->id }}">Independently verify license #{{ $item->id }}</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
  <div class="gm-studio-modal-body">
    <p class="gm-studio-muted text-sm">Review the private document, rights holder, worldwide scope and dates outside the system. This action records your attestation; it does not independently establish authenticity.</p>
    <p class="text-xs mt-3 break-all">Document SHA-256: <code>{{ $item->document_sha256 }}</code></p>
    <input type="hidden" name="document_sha256" value="{{ $item->document_sha256 }}">
    <label class="gm-studio-field"><span>Independent legal verification reference</span><input class="gm-studio-input" name="review_reference" maxlength="180" required placeholder="LEGAL-REVIEW-2026-001"></label>
    <label class="gm-studio-field"><input type="checkbox" name="legal_review_confirmed" value="1" required> <span>I independently checked the document, issuer, license holder, rights scope and effective dates.</span></label>
  </div>
  <div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Record independent verification</button></div>
</form></dialog>
@endif
@if($item->status!=='revoked')
<dialog class="gm-studio-modal" id="rights-evidence-revoke-{{ $item->id }}" aria-labelledby="rights-revoke-heading-{{ $item->id }}">
<form method="POST" action="{{ route('admin.rights-evidence.revoke',$item->id) }}">@csrf
  <div class="gm-studio-modal-heading"><h3 id="rights-revoke-heading-{{ $item->id }}">Revoke license #{{ $item->id }}</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
  <div class="gm-studio-modal-body"><p class="gm-studio-muted text-sm">Revocation immediately blocks reviewed public-HLS eligibility. This action is irreversible.</p>
    <label class="gm-studio-field"><span>Reason for revocation</span><textarea name="reason" class="gm-studio-input" minlength="8" maxlength="255" required rows="3"></textarea></label>
  </div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Revoke license</button></div>
</form></dialog>
@endif
@endforeach
@endsection
