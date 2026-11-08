@extends('layouts.admin')
@section('title', $type === 'movies' ? 'Movies' : 'TV Series')
@section('content')
@php
    $label = $type === 'movies' ? 'Movies' : 'Series';
    $kind = $type === 'movies' ? 'movie' : 'series';
    $mayArchive = in_array(auth()->user()->role, ['admin', 'super_admin'], true);
@endphp

<section class="gm-catalog-manager" aria-labelledby="gm-catalog-manager-title">
  <div class="gm-catalog-heading">
    <div>
      <p class="gm-catalog-kicker">GOMOVIE · CONTENT LIBRARY</p>
      <h2 id="gm-catalog-manager-title">Manage {{ $label }}</h2>
      <p class="gm-catalog-description">Review metadata, manage publishing, and preview your catalog securely.</p>
    </div>
    <a class="gm-catalog-create" href="{{ route('admin.catalog.create', $type) }}">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      New {{ $type === 'movies' ? 'movie' : 'series' }}
    </a>
  </div>

  <form method="GET" action="{{ route('admin.catalog.index', $type) }}" class="gm-catalog-filter" aria-label="Catalog filters">
    <div class="gm-catalog-search">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <label for="gm-catalog-query" class="sr-only">Search titles</label>
      <input id="gm-catalog-query" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($label) }}…" maxlength="100" type="search">
    </div>
    <label for="gm-catalog-status" class="sr-only">Filter by publication status</label>
    <select id="gm-catalog-status" name="status">
      <option value="">All statuses</option>
      @foreach(['draft','published','archived'] as $status)
        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <button type="submit" class="gm-catalog-filter-btn">Apply filters</button>
    @if(request()->filled('q') || request()->filled('status'))
      <a href="{{ route('admin.catalog.index', ['type' => $type, 'per_page' => $perPage]) }}" class="gm-catalog-reset">Clear</a>
    @endif
  </form>

  <div class="gm-catalog-table-head">
    <div>
      <h3>Manage Table <span class="gm-catalog-count">{{ number_format($titles->total()) }}</span></h3>
      <p>Compact view · {{ $type === 'movies' ? 'Movie' : 'TV series' }} records</p>
    </div>
    <form method="GET" action="{{ route('admin.catalog.index', $type) }}" class="gm-catalog-page-size" aria-label="Items per page">
      <input type="hidden" name="q" value="{{ request('q') }}">
      <input type="hidden" name="status" value="{{ request('status') }}">
      <label for="gm-catalog-per-page">Rows per page</label>
      <select name="per_page" id="gm-catalog-per-page" onchange="this.form.requestSubmit()">
        @foreach([10,20,30,40,50] as $count)
          <option value="{{ $count }}" @selected($perPage === $count)>{{ $count }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <form id="gm-catalog-bulk" method="POST" action="{{ route('admin.catalog.bulk', $type) }}" class="gm-catalog-bulk">
    @csrf
    <input type="hidden" name="q" value="{{ request('q') }}">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="gm-catalog-selected"><strong id="gm-selected-count">0</strong> selected on this page</div>
    <label for="gm-bulk-action" class="sr-only">Bulk action</label>
    <select id="gm-bulk-action" name="action" required>
      <option value="">Bulk status action</option>
      <option value="draft">Set to Draft</option>
      <option value="published">Set to Published</option>
    </select>
    <label class="gm-catalog-rights" id="gm-catalog-rights" hidden>
      <input type="checkbox" id="gm-approve-metadata" name="approve_metadata" value="1">
      <span>I confirm metadata and artwork rights, commercial-use permission, and attribution for the selected titles.</span>
    </label>
    <button type="submit" id="gm-catalog-bulk-apply" class="gm-catalog-apply" disabled>Apply to selected</button>
  </form>
  <p class="gm-catalog-bulk-note">Publishing metadata does not grant video rights, activate streams, or enable payments. Removal archives a title and preserves its records.</p>

  <div class="gm-catalog-table-scroll">
    <table class="gm-catalog-table">
      <thead><tr>
        <th class="gm-catalog-check"><label for="gm-select-all" class="sr-only">Select all items on this page</label><input id="gm-select-all" type="checkbox" aria-label="Select all items on this page"></th>
        <th class="gm-catalog-number">No.</th>
        <th>Title</th>
        <th>Year</th>
        <th>Access</th>
        <th>Status</th>
        <th class="gm-catalog-actions-header">Actions</th>
      </tr></thead>
      <tbody>
        @forelse($titles as $title)
          @php
            $poster = \App\Support\CatalogArtwork::url($title->poster_path, 'w342');
            $publicUrl = route('watch.show', [$kind, $title->slug]);
            $previewUrl = route('admin.catalog.preview', [$type, $title->id]);
            $editUrl = route('admin.catalog.edit', [$type, $title->id]);
            $copyUrl = $title->status === 'published' ? $publicUrl : $previewUrl;
            $displayYear = $type === 'movies' ? $title->release_year : $title->first_air_date?->format('Y');
          @endphp
          <tr>
            <td class="gm-catalog-check">
              <input type="checkbox" class="gm-catalog-row-select" name="ids[]" value="{{ $title->id }}" form="gm-catalog-bulk" aria-label="Select {{ $title->title }}">
            </td>
            <td class="gm-catalog-number">{{ ($titles->firstItem() ?? 1) + $loop->index }}</td>
            <td>
              <a href="{{ $editUrl }}" class="gm-catalog-title-link" aria-label="Edit {{ $title->title }}">
                <span class="gm-catalog-thumb">
                  @if($poster)
                    <img src="{{ $poster }}" alt="" loading="lazy" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                    <span hidden class="gm-catalog-thumb-fallback" aria-hidden="true">▶</span>
                  @else
                    <span class="gm-catalog-thumb-fallback" aria-hidden="true">▶</span>
                  @endif
                </span>
                <span class="gm-catalog-title-text">{{ $title->title }}</span>
              </a>
            </td>
            <td class="gm-catalog-muted">{{ $displayYear ?? '—' }}</td>
            <td><span class="gm-catalog-access">{{ $title->is_premium ? 'Premium' : 'Free' }}</span></td>
            <td><span class="gm-catalog-status gm-catalog-status-{{ $title->status }}">{{ ucfirst($title->status) }}</span></td>
            <td class="gm-catalog-actions-cell">
              <button type="button" class="gm-catalog-menu-trigger" data-catalog-trigger
                  data-edit-url="{{ $editUrl }}"
                  data-preview-url="{{ $previewUrl }}"
                  data-copy-url="{{ $copyUrl }}"
                  data-remove-id="{{ $mayArchive ? 'gm-catalog-archive-'.$title->id : '' }}"
                  aria-haspopup="menu" aria-expanded="false"
                  aria-label="Actions for {{ $title->title }}" title="More actions">
                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="12" cy="5" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="12" cy="19" r="1.75"/></svg>
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="gm-catalog-empty">No matching titles. Clear the filters or create a new title.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($mayArchive)
    @foreach($titles as $title)
      <form id="gm-catalog-archive-{{ $title->id }}" method="POST" action="{{ route('admin.catalog.archive', [$type, $title->id]) }}" class="hidden">
        @csrf
      </form>
    @endforeach
  @endif

  <nav aria-label="Catalog pagination" class="gm-catalog-pagination">
    <p>Showing <strong>{{ $titles->firstItem() ?? 0 }}–{{ $titles->lastItem() ?? 0 }}</strong> of <strong>{{ $titles->total() }}</strong></p>
    <div class="gm-catalog-pagination-controls">
      @if($titles->onFirstPage())
        <span class="gm-catalog-page-link is-disabled" aria-disabled="true">Previous</span>
      @else
        <a class="gm-catalog-page-link" href="{{ $titles->previousPageUrl() }}">Previous</a>
      @endif
      <span class="gm-catalog-page-indicator">Page {{ $titles->currentPage() }} / {{ max(1,$titles->lastPage()) }}</span>
      @if($titles->hasMorePages())
        <a class="gm-catalog-page-link" href="{{ $titles->nextPageUrl() }}">Next</a>
      @else
        <span class="gm-catalog-page-link is-disabled" aria-disabled="true">Next</span>
      @endif
      <form method="GET" action="{{ route('admin.catalog.index',$type) }}" class="gm-catalog-goto">
        <input type="hidden" name="q" value="{{ request('q') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <label for="gm-catalog-page">Go to</label>
        <input id="gm-catalog-page" type="number" name="page" value="{{ $titles->currentPage() }}" min="1" max="{{ max(1,$titles->lastPage()) }}" required>
        <button type="submit">Go</button>
      </form>
    </div>
  </nav>
</section>

<div id="gm-catalog-floating-menu" class="gm-catalog-floating-menu" role="menu" aria-label="Title actions" hidden></div>
<div id="gm-catalog-notice" role="status" aria-live="polite" class="gm-catalog-notice" hidden></div>

<style>
.gm-catalog-manager{color:#f5f3f7;min-width:0}
.gm-catalog-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:21px}
.gm-catalog-kicker{font-size:10px;letter-spacing:.22em;font-weight:800;color:#f8c451;margin-bottom:7px}
.gm-catalog-heading h2{font-size:clamp(23px,2.5vw,30px);font-weight:850;letter-spacing:-.035em}
.gm-catalog-description{font-size:12px;color:#a7a1ae;margin-top:5px}
.gm-catalog-create,.gm-catalog-filter-btn,.gm-catalog-apply{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #f43f5e55;border-radius:10px;padding:10px 16px;font-size:12px;font-weight:800;background:linear-gradient(120deg,#ff314f,#b61337);color:white;text-decoration:none;cursor:pointer}
.gm-catalog-create:hover,.gm-catalog-filter-btn:hover,.gm-catalog-apply:hover:not(:disabled){filter:brightness(1.12)}
.gm-catalog-apply:disabled{opacity:.45;cursor:not-allowed}
.gm-catalog-filter{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 21px}
.gm-catalog-search{position:relative;min-width:200px;flex:1 1 280px}
.gm-catalog-search svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#96909f}
.gm-catalog-search input{padding-left:41px!important;width:100%}
.gm-catalog-filter input,.gm-catalog-filter select,.gm-catalog-page-size select,.gm-catalog-bulk select,.gm-catalog-goto input{height:39px;border:1px solid #393640;border-radius:10px;background:#17161c;color:#f7f6f8;padding:0 13px;font-size:12px;outline:none}
.gm-catalog-filter select{min-width:130px}
.gm-catalog-filter-btn{min-height:39px;padding-block:0;background:#25232b;border-color:#48434e}
.gm-catalog-reset{font-size:12px;font-weight:700;color:#f1c56b;text-decoration:none}
.gm-catalog-table-head{padding:16px 18px 13px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:13px;border:1px solid #302d36;border-radius:16px 16px 0 0;background:linear-gradient(120deg,#1a1920,#141319)}
.gm-catalog-table-head h3{font-size:16px;font-weight:850;letter-spacing:-.02em}
.gm-catalog-count{font-size:11px;color:#ddd5e5;background:#312b39;border-radius:20px;padding:3px 8px;margin-left:5px}
.gm-catalog-table-head p{font-size:11px;color:#938d9b;margin-top:3px}
.gm-catalog-page-size{display:flex;align-items:center;gap:10px;font-size:12px;color:#aaa3b2}
.gm-catalog-page-size select{height:34px;font-weight:750;min-width:72px}
.gm-catalog-bulk{display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:10px 18px;border:1px solid #302d36;border-top:0;background:#131218}
.gm-catalog-selected{font-size:12px;color:#b7b0c0}
.gm-catalog-selected strong{font-size:14px;color:white}
.gm-catalog-bulk select{height:34px;min-width:160px}
.gm-catalog-apply{min-height:34px;padding:6px 13px}
.gm-catalog-rights{display:flex;align-items:center;gap:8px;max-width:540px;font-size:11px;color:#e2cfae;line-height:1.45}
.gm-catalog-rights[hidden]{display:none}
.gm-catalog-rights input{accent-color:#ef3458;flex:0 0 auto}
.gm-catalog-bulk-note{padding:8px 18px;margin:0;border:1px solid #302d36;border-top:0;background:#111016;color:#827d8c;font-size:10px;line-height:1.6}
.gm-catalog-table-scroll{overflow-x:auto;border:1px solid #302d36;border-top:0;border-radius:0 0 16px 16px;background:#141318}
.gm-catalog-table{width:100%;min-width:730px;border-collapse:collapse;text-align:left}
.gm-catalog-table thead{background:#1b1a20}
.gm-catalog-table th{padding:11px 12px;color:#9b94a3;font-size:10px;letter-spacing:.07em;text-transform:uppercase;font-weight:850;white-space:nowrap}
.gm-catalog-table td{padding:8px 12px;color:#ded9e2;font-size:12px;line-height:1.3;vertical-align:middle;border-top:1px solid #27252e}
.gm-catalog-table tbody tr:hover{background:#211c26}
.gm-catalog-table input[type=checkbox],#gm-select-all{width:15px;height:15px;accent-color:#f43f5e;cursor:pointer}
.gm-catalog-check{width:40px;text-align:center!important}
.gm-catalog-number{width:56px;color:#88818f!important;font-variant-numeric:tabular-nums}
.gm-catalog-title-link{display:flex;align-items:center;gap:11px;min-width:220px;max-width:450px;text-decoration:none;color:#f7f5f8;font-size:12px;font-weight:750}
.gm-catalog-title-link:hover .gm-catalog-title-text{color:#ffd179}
.gm-catalog-title-text{min-width:0;line-height:1.4}
.gm-catalog-thumb{display:grid;place-items:center;position:relative;overflow:hidden;flex:0 0 37px;width:37px;height:53px;border-radius:7px;border:1px solid #ffffff20;background:radial-gradient(circle at 50% 20%,#77314e,#1b1222 75%)}
.gm-catalog-thumb img{width:100%;height:100%;object-fit:cover}
.gm-catalog-thumb-fallback{color:#e1baba;font-size:14px}
.gm-catalog-thumb-fallback[hidden]{display:none}
.gm-catalog-muted{color:#aaa4b2!important;white-space:nowrap}
.gm-catalog-access{font-size:11px;color:#afa8b5}
.gm-catalog-status{display:inline-flex;align-items:center;justify-content:center;border:1px solid #45404b;background:#1d1c22;padding:4px 9px;border-radius:20px;font-size:10px;font-weight:800;white-space:nowrap}
.gm-catalog-status-published{border-color:#245743;background:#102b24;color:#75e4a8}
.gm-catalog-status-draft{color:#bfb7c7}
.gm-catalog-status-archived{color:#f6c378;border-color:#5f4936;background:#34251d}
.gm-catalog-actions-header,.gm-catalog-actions-cell{text-align:center;width:75px}
.gm-catalog-menu-trigger{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9px;color:#e9dce8;border:1px solid transparent;background:transparent;cursor:pointer}
.gm-catalog-menu-trigger:hover,.gm-catalog-menu-trigger[aria-expanded=true]{background:#33303a;border-color:#514658}
.gm-catalog-empty{text-align:center!important;padding:34px!important;color:#9b94a2!important}
.gm-catalog-pagination{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:18px;padding:17px 2px;font-size:12px;color:#a49da9}
.gm-catalog-pagination strong{color:#eae4eb}
.gm-catalog-pagination-controls,.gm-catalog-goto{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.gm-catalog-page-link,.gm-catalog-goto button{padding:8px 12px;border:1px solid #423c49;border-radius:9px;color:#e1dce4;background:#1a181f;text-decoration:none;font-size:12px;font-weight:750}
.gm-catalog-page-link:not(.is-disabled):hover,.gm-catalog-goto button:hover{border-color:#a94562;color:white}
.gm-catalog-page-link.is-disabled{opacity:.35}
.gm-catalog-page-indicator{font-variant-numeric:tabular-nums;color:#c2b9ca}
.gm-catalog-goto label{font-size:11px;white-space:nowrap}
.gm-catalog-goto input{height:33px;width:65px;text-align:center;padding-inline:4px}
.gm-catalog-floating-menu{position:fixed;z-index:1000;min-width:172px;padding:5px;border-radius:12px;border:1px solid #554558;background:#231d29;box-shadow:0 20px 55px #000a}
.gm-catalog-floating-menu[hidden]{display:none}
.gm-catalog-floating-menu a,.gm-catalog-floating-menu button{width:100%;display:flex;align-items:center;gap:10px;border:0;border-radius:7px;background:transparent;padding:11px 12px;font-size:12px;color:#f6f0f7;text-align:left;text-decoration:none;cursor:pointer}
.gm-catalog-floating-menu a:hover,.gm-catalog-floating-menu button:hover,.gm-catalog-floating-menu a:focus-visible,.gm-catalog-floating-menu button:focus-visible{background:#423142}
.gm-catalog-floating-menu [data-kind=remove]{color:#ffadb4}
.gm-catalog-floating-menu .gm-catalog-menu-icon{width:16px;text-align:center;color:#e4bdbd}
.gm-catalog-notice{position:fixed;top:20px;right:20px;z-index:1100;max-width:min(390px,calc(100vw - 40px));border:1px solid #387654;border-radius:12px;background:#112a20;padding:13px 17px;box-shadow:0 12px 38px #0008;color:#bdf1cf;font-size:12px}
.gm-catalog-notice[hidden]{display:none}
@media(max-width:680px){
 .gm-catalog-heading{align-items:flex-start}
 .gm-catalog-filter{gap:8px}
 .gm-catalog-search{flex-basis:100%}
 .gm-catalog-bulk{align-items:flex-start}
 .gm-catalog-pagination-controls{width:100%}
 .gm-catalog-page-indicator{margin-left:auto}
 .gm-catalog-goto{width:100%;justify-content:flex-end}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const bulk = document.getElementById('gm-catalog-bulk');
  const action = document.getElementById('gm-bulk-action');
  const apply = document.getElementById('gm-catalog-bulk-apply');
  const selectAll = document.getElementById('gm-select-all');
  const checks = Array.from(document.querySelectorAll('.gm-catalog-row-select'));
  const selectedCount = document.getElementById('gm-selected-count');
  const rights = document.getElementById('gm-catalog-rights');
  const approved = document.getElementById('gm-approve-metadata');

  function syncSelections() {
    const selected = checks.filter(x => x.checked).length;
    selectedCount.textContent = String(selected);
    apply.disabled = selected === 0 || !action.value;
    if (checks.length) {
      selectAll.checked = selected === checks.length;
      selectAll.indeterminate = selected > 0 && selected < checks.length;
    }
    rights.hidden = action.value !== 'published';
  }
  selectAll.addEventListener('change', () => {
    checks.forEach(x => { x.checked = selectAll.checked; });
    syncSelections();
  });
  checks.forEach(x => x.addEventListener('change', syncSelections));
  action.addEventListener('change', syncSelections);
  bulk.addEventListener('submit', function (event) {
    if (!checks.some(x => x.checked) || !action.value) {
      event.preventDefault(); return;
    }
    if (action.value === 'published') {
      if (!approved.checked) {
        event.preventDefault(); approved.focus();
        approved.setCustomValidity('Confirm metadata and artwork rights before publishing.');
        approved.reportValidity(); return;
      }
      if (!window.confirm('Publish the selected catalog metadata? This does not grant streaming rights.')) {
        event.preventDefault();
      }
    } else if (!window.confirm('Return the selected titles to Draft? They will no longer appear publicly.')) {
      event.preventDefault();
    }
  });
  approved.addEventListener('change', () => approved.setCustomValidity(''));
  syncSelections();

  const menu = document.getElementById('gm-catalog-floating-menu');
  const notice = document.getElementById('gm-catalog-notice');
  let activeTrigger = null;
  let noticeTimer = null;
  function showNotice(message) {
    notice.textContent = message;
    notice.hidden = false;
    window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => notice.hidden = true, 3000);
  }
  function closeMenu() {
    menu.hidden = true;
    menu.replaceChildren();
    if (activeTrigger) activeTrigger.setAttribute('aria-expanded','false');
    activeTrigger = null;
  }
  function link(label, symbol, href) {
    const el = document.createElement('a');
    el.setAttribute('role','menuitem');
    el.href = href;
    const ico = document.createElement('span');
    ico.className = 'gm-catalog-menu-icon';
    ico.setAttribute('aria-hidden','true');
    ico.textContent = symbol;
    el.append(ico, document.createTextNode(label));
    menu.append(el);
  }
  function button(label, symbol, callback, kind) {
    const el = document.createElement('button');
    el.type = 'button';
    el.setAttribute('role','menuitem');
    if (kind) el.dataset.kind = kind;
    const ico = document.createElement('span');
    ico.className = 'gm-catalog-menu-icon';
    ico.setAttribute('aria-hidden','true');
    ico.textContent = symbol;
    el.append(ico, document.createTextNode(label));
    el.addEventListener('click', callback);
    menu.append(el);
  }
  function openMenu(trigger) {
    closeMenu();
    activeTrigger = trigger;
    trigger.setAttribute('aria-expanded','true');
    link('Edit', '✎', trigger.dataset.editUrl);
    link('Preview', '◉', trigger.dataset.previewUrl);
    if (trigger.dataset.removeId) {
      button('Remove', '×', () => {
        const form = document.getElementById(trigger.dataset.removeId);
        if (form && window.confirm('Archive this title and remove it from the public catalog? No data will be deleted.')) {
          form.requestSubmit();
        }
        closeMenu();
      }, 'remove');
    }
    button('Copy Link', '⧉', async () => {
      const value = trigger.dataset.copyUrl;
      try {
        if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(value);
        showNotice('Link copied to clipboard');
      } catch (error) {
        window.prompt('Copy this catalog link', value);
      }
      closeMenu();
    });
    menu.hidden = false;
    const rect = trigger.getBoundingClientRect();
    const bounds = menu.getBoundingClientRect();
    const left = Math.min(window.innerWidth - bounds.width - 12, Math.max(12, rect.right - bounds.width));
    const top = rect.bottom + bounds.height + 10 > window.innerHeight
      ? Math.max(12, rect.top - bounds.height - 5)
      : rect.bottom + 5;
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    menu.querySelector('[role=menuitem]')?.focus();
  }
  document.querySelectorAll('[data-catalog-trigger]').forEach(trigger => {
    trigger.addEventListener('click', event => {
      event.stopPropagation();
      if (activeTrigger === trigger) closeMenu();
      else openMenu(trigger);
    });
  });
  document.addEventListener('click', event => {
    if (!menu.hidden && !menu.contains(event.target)) closeMenu();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      const trigger = activeTrigger; closeMenu(); trigger?.focus();
    }
  });
  window.addEventListener('scroll', closeMenu, {passive:true});
  window.addEventListener('resize', closeMenu);
});
</script>
@endsection
