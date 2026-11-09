<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0b0d14">
<title>Upload Studio — Gomovie</title>
@include('partials.feedback')
<style>
:root{color-scheme:dark;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#090b11;color:#f7f7fb}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
a{color:inherit;text-decoration:none}
[hidden]{display:none!important}
.header{border-bottom:1px solid #ffffff1a;background:#10131b}
.header-inner{max-width:1360px;margin:0 auto;min-height:74px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 27px}
.brand{font-weight:900;font-size:23px;letter-spacing:-.06em}
.brand em{font-style:normal;color:#f02b55}
.header-actions{display:flex;align-items:center;gap:10px}
.quiet-link{display:inline-flex;align-items:center;justify-content:center;min-height:39px;padding:8px 15px;border:1px solid #ffffff25;border-radius:10px;color:#c2c8d7;font-weight:700;font-size:12px}
.quiet-link:hover{color:white;border-color:#f04265}
.workspace{max-width:1360px;margin:0 auto;padding:36px 26px 85px}
.kicker{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#f67f95;font-weight:900}
.heading{font-size:clamp(31px,4vw,42px);letter-spacing:-.045em;margin:8px 0 7px;line-height:1.12}
.subheading{font-size:14px;color:#aab4c7;line-height:1.6;margin:0}
.steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:27px 0 23px;background:#101521;padding:7px;border:1px solid #ffffff18;border-radius:17px}
.step{min-height:52px;padding:9px 8px;border:0;border-radius:12px;background:transparent;color:#96a3b8;font-weight:760;font-size:12px;display:flex;align-items:center;justify-content:center;gap:9px;text-align:center}
.step:hover:not(:disabled){background:#202637;color:white}
.step:disabled{cursor:not-allowed;opacity:.48}
.step[aria-current="step"]{color:white;background:#e81c4d;box-shadow:0 6px 18px #f00b4735}
.step-number{width:25px;height:25px;flex:none;display:grid;place-items:center;border:1px solid #ffffff38;border-radius:8px;font-size:11px}
.step[aria-current="step"] .step-number{border-color:#ffffff75}
.columns{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(280px,.88fr);align-items:start;gap:22px}
.card{background:#111520;border:1px solid #ffffff1c;border-radius:19px;overflow:hidden}
.card-inner{padding:clamp(22px,3vw,34px)}
.panel-title{display:flex;align-items:center;gap:12px;margin-bottom:21px}
.panel-title h2{font-size:21px;letter-spacing:-.035em;margin:0}
.panel-title .count{width:32px;height:32px;flex:none;display:grid;place-items:center;border-radius:10px;background:#e6215025;border:1px solid #ef426650;color:#ff8ba1;font-weight:850}
.section-help{color:#a6b0c2;font-size:13px;margin:-8px 0 22px;line-height:1.65}
.label{display:block;font-size:12px;color:#ecedf3;font-weight:750;margin-bottom:9px}
.field{margin-bottom:19px}
.field:last-child{margin-bottom:0}
.control{width:100%;background:#090d17;border:1px solid #30394c;border-radius:11px;padding:11px 13px;min-height:45px;color:#f7f7fb;outline:0;font-size:13px}
.control::placeholder{color:#7d889c}
.control:focus{border-color:#f44d77;box-shadow:0 0 0 3px #f44d771c}
textarea.control{resize:vertical;min-height:125px;line-height:1.55}
.control option{color:#f7f7fb;background:#111520}
.field-note{color:#91a0b5;font-size:11px;margin:7px 0 0;line-height:1.5}
.type-select{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
.type-select label{position:relative}
.type-select input{position:absolute;opacity:0;width:1px;height:1px}
.type-select span{display:flex;align-items:center;justify-content:center;min-width:125px;min-height:43px;border:1px solid #384055;border-radius:11px;background:#0c111b;color:#a8b4c7;font-size:13px;font-weight:760;padding:10px 15px}
.type-select input:checked+span{color:white;border-color:#ef4168;background:#e5244e22}
.type-select input:focus-visible+span{outline:2px solid #f37894;outline-offset:2px}
.row-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:12px;min-height:208px;padding:24px;border:2px dashed #3a4860;border-radius:16px;background:#0a111f;cursor:pointer;margin-top:21px}
.drop:hover,.drop.dragging,.drop:focus-within{border-color:#ed4a70;background:#151522}
.drop input{opacity:0;position:absolute;inset:0;width:100%;height:100%;cursor:pointer}
.upload-glyph{width:55px;height:55px;border-radius:16px;background:#e9204d26;color:#ff6689;font-size:30px;display:grid;place-items:center}
.filename{font-size:15px;font-weight:780;overflow-wrap:anywhere}
.filemeta{font-size:12px;color:#9daac0}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;border-radius:11px;padding:11px 20px;border:1px solid #3c465a;background:#151b29;color:#e7eaf3;font-weight:780;font-size:13px}
.btn:hover{background:#252d3e}
.btn-primary{border-color:#e9244e;background:#e9244e;color:white}
.btn-primary:hover{background:#f13c63}
.btn:disabled{opacity:.5;cursor:not-allowed}
.bottom-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;border-top:1px solid #ffffff18;padding-top:22px;margin-top:24px}
.bottom-actions .push{flex:1}
.progress-wrap{margin-top:21px}
.progress-head{display:flex;justify-content:space-between;color:#b9c1d1;font-size:12px;margin-bottom:9px}
.progress-track{height:8px;background:#293142;border-radius:20px;overflow:hidden}
.progress-fill{width:0;height:100%;background:linear-gradient(90deg,#fa8257,#ed2553);transition:width .16s}
.message{background:#292132;border:1px solid #ffffff24;padding:12px 14px;border-radius:11px;color:#e0dce8;font-size:13px;line-height:1.55;margin:19px 0 0;overflow-wrap:anywhere}
.message.error{background:#361820;border-color:#ad3a52;color:#ffb4c3}
.message.ok{background:#123327;border-color:#26895c;color:#adf5d4}
.summary-title{color:#96a1b6;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:800}
.summary-value{font-size:14px;font-weight:740;overflow-wrap:anywhere;margin-top:7px}
.sidebar-content{padding:24px}
.sidebar-heading{font-size:14px;font-weight:800;margin:0 0 22px}
.sidebar-block+.sidebar-block{border-top:1px solid #ffffff14;padding-top:18px;margin-top:18px}
.sidebar-foot{padding:18px 23px;background:#0d1420;border-top:1px solid #ffffff17;color:#9faabe;font-size:12px;line-height:1.65}
.preview{width:100%;border:1px solid #374154;background:#090e18;border-radius:11px;max-height:250px;object-fit:contain;margin-top:12px}
.artwork-select{display:flex;gap:8px}
.artwork-select label{flex:1}
.artwork-select input{accent-color:#ed2855}
.access-option{display:flex;gap:12px;align-items:flex-start;border:1px solid #394258;background:#0b101b;padding:17px;border-radius:13px;cursor:pointer;margin-bottom:12px}
.access-option:has(input:checked){border-color:#ed315d;background:#e6215016}
.access-option input{accent-color:#ed315d;margin-top:4px}
.access-option b{font-size:14px}
.access-option small{display:block;color:#a6b1c5;margin-top:5px;line-height:1.5}
.notice{background:#101d2b;border:1px solid #2e465c;border-radius:11px;padding:13px 15px;color:#b7cde3;font-size:12px;line-height:1.6}
.big-check{width:65px;height:65px;display:grid;place-items:center;border-radius:19px;background:#163e2b;color:#65e5a4;font-size:36px;margin-bottom:16px}
.status-chip{display:inline-flex;padding:6px 11px;border-radius:20px;border:1px solid #465269;color:#cad2e1;font-size:12px;font-weight:750}
@media(max-width:1000px){.columns{grid-template-columns:1fr}.sidebar-content{padding:20px}.sidebar{order:2}}
@media(max-width:700px){.header-inner{padding:0 15px;min-height:66px}.quiet-link{font-size:11px;padding:7px 10px}.workspace{padding:24px 13px 60px}.steps{grid-template-columns:repeat(5,minmax(130px,1fr));overflow-x:auto;scrollbar-width:thin}.step{min-height:48px}.card-inner{padding:20px 17px}.row-fields{grid-template-columns:1fr}.type-select label{flex:1}.type-select span{min-width:0}}
@media(prefers-reduced-motion:reduce){.progress-fill{transition:none}}
</style>
</head>
<body>
<header class="header"><div class="header-inner">
  <a href="{{ route('admin.dashboard') }}" class="brand" aria-label="Gomovie dashboard"><em>GO</em>MOVIE</a>
  <div class="header-actions">
    <a href="{{ route('admin.media.index') }}" class="quiet-link">Media Library</a>
    <a href="{{ route('admin.dashboard') }}" class="quiet-link">Dashboard</a>
  </div>
</div></header>
<main class="workspace">
  <span class="kicker">Gomovie Studio</span>
  <h1 class="heading">Upload Studio</h1>
  <p class="subheading">Upload a video first. Add details and artwork only if you need them.</p>
  <nav class="steps" aria-label="Upload Studio stages">
    <button type="button" class="step" data-step="1" aria-current="step"><span class="step-number">1</span>File &amp; Ingest</button>
    <button type="button" class="step" data-step="2" disabled><span class="step-number">2</span>Details &amp; Meta</button>
    <button type="button" class="step" data-step="3" disabled><span class="step-number">3</span>Poster &amp; Assets</button>
    <button type="button" class="step" data-step="4" disabled><span class="step-number">4</span>Catalog Access</button>
    <button type="button" class="step" data-step="5" disabled><span class="step-number">5</span>Finish</button>
  </nav>
  <div class="columns">
    <div class="card">
      <form id="gm-upload-studio-form" action="{{ route('upload-studio.upload') }}" method="POST" enctype="multipart/form-data" data-max-bytes="{{ $maxUploadBytes }}" data-resume-id="{{ $resume->id ?? '' }}" data-resume-type="{{ $resume->ingestable_type ?? '' }}" data-resume-target="{{ $resume->ingestable_id ?? '' }}" data-upload-base="{{ route('upload-studio.index') }}" novalidate>
        @csrf
        <section class="card-inner" data-panel="1">
          <div class="panel-title"><span class="count">1</span><h2>File &amp; Ingest</h2></div>
          <p class="section-help">Choose an existing movie or TV episode and upload the original video.</p>
          <div class="type-select" role="group" aria-label="Content type">
            <label><input name="type" type="radio" value="movie" checked><span>Movie</span></label>
            <label><input name="type" type="radio" value="episode"><span>TV episode</span></label>
          </div>
          <div class="field">
            <label for="gm-upload-search" class="label">Find a title</label>
            <input class="control" type="search" id="gm-upload-search" placeholder="Search movies and episodes" autocomplete="off">
          </div>
          <div class="field">
            <label for="gm-upload-target" class="label">Select movie or episode</label>
            <select class="control" name="title_id" id="gm-upload-target" required>
              <option value="">Select an existing title</option>
              @foreach($movies as $movie)
                <option data-kind="movie" data-title="{{ $movie->title }}" data-overview="{{ $movie->overview }}" data-runtime="{{ $movie->runtime_minutes }}" data-year="{{ $movie->release_year }}" data-premium="{{ (int) $movie->is_premium }}" data-catalog-status="{{ $movie->status }}" data-art-poster="{{ \App\Support\CatalogArtwork::url($movie->poster_path, 'w342') }}" data-art-backdrop="{{ \App\Support\CatalogArtwork::url($movie->backdrop_path, 'w780') }}" value="{{ $movie->id }}">{{ $movie->title }}</option>
              @endforeach
              @foreach($episodes as $episode)
                <option data-kind="episode" data-title="{{ $episode->title }}" data-overview="{{ $episode->overview }}" data-runtime="{{ $episode->runtime_minutes }}" data-year="" data-premium="{{ (int) ($episode->season?->series?->is_premium ?? 0) }}" data-catalog-status="{{ $episode->status }}" data-series-status="{{ $episode->season?->series?->status ?? '' }}" data-art-still="{{ \App\Support\CatalogArtwork::url($episode->still_path, 'w780') }}" value="{{ $episode->id }}">{{ $episode->season?->series?->title ?? 'Series' }} · S{{ $episode->season?->season_number ?? '?' }} E{{ $episode->episode_number }} — {{ $episode->title }}</option>
              @endforeach
            </select>
            <p class="field-note" id="gm-no-matches" hidden>No matching titles.</p>
          </div>
          <label class="drop" id="gm-upload-drop">
            <span class="upload-glyph" aria-hidden="true">↑</span>
            <span class="filename" id="gm-upload-file-label">Select or drop your video</span>
            <span class="filemeta" id="gm-upload-file-meta">MP4, MOV, MKV, WebM · Max {{ number_format($maxUploadBytes / 1048576, 1) }} MB</span>
            <input id="gm-upload-file" name="video" type="file" accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" aria-label="Select video file" required>
          </label>
          <div id="gm-upload-progress-wrap" class="progress-wrap" hidden>
            <div class="progress-head"><span id="gm-upload-progress-text">Uploading…</span><strong id="gm-upload-percent">0%</strong></div>
            <div class="progress-track" id="gm-upload-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-fill" id="gm-upload-progress-fill"></div></div>
          </div>
          <p class="message" id="gm-upload-feedback" role="status" aria-live="polite" hidden></p>
          <div class="bottom-actions">
            <button type="submit" class="btn btn-primary" id="gm-upload-submit">Upload video →</button>
            <button type="button" class="btn" id="gm-upload-cancel" hidden>Cancel</button>
            <span class="push"></span>
            <a class="btn" href="{{ route('admin.media.index') }}" id="gm-upload-finish" hidden>Finish now</a>
            <button type="button" class="btn" id="gm-upload-next" hidden>Continue to details →</button>
          </div>
        </section>

        <section class="card-inner" data-panel="2" hidden>
          <div class="panel-title"><span class="count">2</span><h2>Details &amp; Meta</h2></div>
          <p class="section-help">Optional. These fields update the selected catalog title, not just this video.</p>
          <div class="field">
            <label for="gm-meta-title" class="label">Title</label>
            <input class="control" id="gm-meta-title" maxlength="255" placeholder="Title">
          </div>
          <div class="field">
            <label for="gm-meta-overview" class="label">Synopsis</label>
            <textarea class="control" id="gm-meta-overview" maxlength="12000" rows="5" placeholder="Describe the video"></textarea>
          </div>
          <div class="row-fields">
            <div class="field" id="gm-meta-year-field">
              <label for="gm-meta-year" class="label">Release year</label>
              <input class="control" id="gm-meta-year" type="number" min="1888" max="2100" placeholder="Optional">
            </div>
            <div class="field">
              <label for="gm-meta-runtime" class="label">Runtime (minutes)</label>
              <input class="control" id="gm-meta-runtime" type="number" min="1" max="1440" placeholder="Optional">
            </div>
          </div>
          <div id="gm-meta-readonly" class="notice" hidden>This title is already published or archived. Edit its details from Catalog Manager instead.</div>
          <p class="message" id="gm-meta-feedback" role="status" aria-live="polite" hidden></p>
          <div class="bottom-actions">
            <button class="btn" type="button" data-prev="1">← Back</button>
            <span class="push"></span>
            <button class="btn btn-primary" type="button" id="gm-save-details">Save details</button>
            <button class="btn" type="button" data-next="3">Continue →</button>
          </div>
        </section>

        <section class="card-inner" data-panel="3" hidden>
          <div class="panel-title"><span class="count">3</span><h2>Poster &amp; Assets</h2></div>
          <p class="section-help">Optional. Upload a real image for the selected title. No simulated video frames.</p>
          <div class="field">
            <label for="gm-artwork-slot" class="label">Image placement</label>
            <select id="gm-artwork-slot" class="control">
              <option value="poster">Movie poster (2:3)</option>
              <option value="backdrop">Movie backdrop (16:9)</option>
            </select>
          </div>
          <div class="field" id="gm-existing-artwork" hidden>
            <span class="label">Already saved for this title</span>
            <img id="gm-existing-artwork-preview" class="preview" alt="Existing title artwork" loading="lazy">
            <p class="field-note">This image is already available. Select a different file only if you want to replace it.</p>
          </div>
          <div class="field">
            <label for="gm-artwork-file" class="label">Choose artwork (JPG, PNG or WebP · Max 8 MB)</label>
            <input class="control" id="gm-artwork-file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <img id="gm-artwork-preview" class="preview" alt="Selected artwork preview" hidden>
          </div>
          <div id="gm-artwork-readonly" class="notice" hidden>This title is not a draft, so artwork changes are unavailable here.</div>
          <p class="message" id="gm-artwork-feedback" role="status" aria-live="polite" hidden></p>
          <div class="bottom-actions">
            <button class="btn" type="button" data-prev="2">← Back</button>
            <span class="push"></span>
            <button class="btn btn-primary" type="button" id="gm-save-artwork">Save artwork</button>
            <button class="btn" type="button" data-next="4">Continue →</button>
          </div>
        </section>

        <section class="card-inner" data-panel="4" hidden>
          <div class="panel-title"><span class="count">4</span><h2>Catalog Access</h2></div>
          <p class="section-help">Optional. Use Gomovie's existing Free/Premium catalog classification. This does not activate paid billing.</p>
          <label class="access-option">
            <input type="radio" name="studio_premium" value="0">
            <span><b>Free catalog</b><small>Mark this title as free in the catalog.</small></span>
          </label>
          <label class="access-option">
            <input type="radio" name="studio_premium" value="1" checked>
            <span><b>Premium catalog</b><small>Set the existing premium flag. Payments and subscriptions remain disabled.</small></span>
          </label>
          <p class="field-note" id="gm-access-series-note" hidden>For an episode, this setting applies to its entire parent series.</p>
          <div class="notice" id="gm-access-readonly" hidden>This catalog title is not an editable draft.</div>
          <p class="message" id="gm-access-feedback" role="status" aria-live="polite" hidden></p>
          <div class="bottom-actions">
            <button class="btn" type="button" data-prev="3">← Back</button>
            <span class="push"></span>
            <button class="btn btn-primary" type="button" id="gm-save-access">Save access</button>
            <button class="btn" type="button" data-next="5">Continue →</button>
          </div>
        </section>

        <section class="card-inner" data-panel="5" hidden>
          <div class="panel-title"><span class="count">5</span><h2>Finish</h2></div>
          <div class="big-check" aria-hidden="true">✓</div>
          <h3 style="margin:0 0 8px;font-size:21px">Video saved successfully</h3>
          <p class="section-help" style="margin:0 0 20px">Your original file is stored privately. Publishing and streaming are not activated by uploading.</p>
          <div class="notice"><strong>Actual upload status: </strong><span id="gm-finish-status">Saved privately</span></div>
          <p class="message" id="gm-finish-feedback" role="status" aria-live="polite" hidden></p>
          <div class="bottom-actions">
            <button class="btn" type="button" data-prev="4">← Back</button>
            <button class="btn" type="button" id="gm-refresh-status">Refresh status</button>
            <span class="push"></span>
            <a href="{{ route('admin.media.index') }}" class="btn btn-primary">Open Media Library →</a>
          </div>
        </section>
      </form>
    </div>
    <aside class="card sidebar">
      <div class="sidebar-content">
        <h2 class="sidebar-heading">Your upload</h2>
        <div class="sidebar-block">
          <div class="summary-title">Selected title</div>
          <div class="summary-value" id="gm-side-title">Choose a title to begin</div>
        </div>
        <div class="sidebar-block">
          <div class="summary-title">Video file</div>
          <div class="summary-value" id="gm-side-file">No file selected</div>
        </div>
        <div class="sidebar-block">
          <div class="summary-title">Upload status</div>
          <div class="summary-value"><span id="gm-side-status" class="status-chip">Not uploaded</span></div>
        </div>
        <div class="sidebar-block" id="gm-side-resume-wrap" hidden>
          <a id="gm-side-resume" class="quiet-link" href="#">Link to this upload</a>
        </div>
      </div>
      <div class="sidebar-foot">Supported formats and upload size are determined by your server. Files are private until the separate publishing requirements are met.</div>
    </aside>
  </div>
</main>
<script>
(() => {
  'use strict';
  const form = document.getElementById('gm-upload-studio-form');
  if (!form) return;
  const el = id => document.getElementById(id);
  const types = [...form.querySelectorAll('input[name="type"]')];
  const target = el('gm-upload-target');
  const search = el('gm-upload-search');
  const videoFile = el('gm-upload-file');
  const drop = el('gm-upload-drop');
  const submit = el('gm-upload-submit');
  const cancel = el('gm-upload-cancel');
  const steps = [...document.querySelectorAll('[data-step]')];
  const panels = [...document.querySelectorAll('[data-panel]')];
  const token = form.querySelector('input[name="_token"]')?.value || '';
  const maxBytes = Number(form.dataset.maxBytes || 0);
  const catalog = [...target.options].filter(o => o.value).map(o => ({
    kind: o.dataset.kind, id: o.value, label: o.textContent,
    title: o.dataset.title || '', overview: o.dataset.overview || '',
    runtime: o.dataset.runtime || '', year: o.dataset.year || '',
    premium: o.dataset.premium || '0', status: o.dataset.catalogStatus || 'draft',
    seriesStatus: o.dataset.seriesStatus || '',
    artwork: {poster: o.dataset.artPoster || '', backdrop: o.dataset.artBackdrop || '', still: o.dataset.artStill || ''}
  }));
  const limitsLabel = el('gm-upload-file-meta').textContent;
  let ingestId = form.dataset.resumeId || '';
  let ingestStatus = ingestId ? 'quarantined' : '';
  let currentStep = 1;
  let xhr = null;
  let previewObjectUrl = null;
  const resumeType = form.dataset.resumeType || '';
  const resumeTarget = form.dataset.resumeTarget || '';

  function currentType() { return form.querySelector('input[name="type"]:checked')?.value || ''; }
  function currentItem() { return catalog.find(c => c.kind === currentType() && c.id === target.value); }
  function displayStatus(status) {
    const known = {
      quarantined: 'Saved privately', approved: 'Private processing authorized',
      processing: 'Processing privately', encoded: 'Private encoding completed',
      rejected: 'Rejected', failed: 'Processing failed'
    };
    return known[status] || status || 'Not uploaded';
  }
  function message(id, text, state) {
    const item = el(id);
    if (!item) return;
    // Global, top-right confirmation. Keep field-level validation next to its input.
    if (state === 'ok') {
      item.hidden = true;
      window.GomovieFeedback?.show(text, 'success');
      return;
    }
    item.textContent = text;
    item.className = 'message' + (state === 'error' ? ' error' : '');
    item.hidden = false;
  }
  function clearMessage(id) { el(id).hidden = true; }
  function selectStep(n) {
    if (n > 1 && !ingestId) return;
    currentStep = n;
    steps.forEach(b => { b.setAttribute('aria-current', Number(b.dataset.step) === n ? 'step' : 'false'); b.disabled = !ingestId && Number(b.dataset.step) > 1; });
    panels.forEach(p => p.hidden = Number(p.dataset.panel) !== n);
    if (n === 5) refreshStatus();
    document.querySelector('.steps')?.scrollIntoView({behavior:'smooth',block:'nearest'});
  }
  function filterTitles() {
    const former = target.value;
    const query = search.value.trim().toLocaleLowerCase();
    const matches = catalog.filter(c => c.kind === currentType() && c.label.toLocaleLowerCase().includes(query));
    target.replaceChildren(new Option('Select an existing title', ''));
    for (const c of matches) target.add(new Option(c.label, c.id));
    if (matches.some(c => c.id === former)) target.value = former;
    el('gm-no-matches').hidden = matches.length > 0;
    syncForms();
  }
  function syncExistingArtwork() {
    const entry = currentItem();
    const slot = el('gm-artwork-slot').value;
    const url = entry?.artwork?.[slot] || '';
    const wrapper = el('gm-existing-artwork');
    const preview = el('gm-existing-artwork-preview');
    wrapper.hidden = !url;
    if (url) { preview.src = url; preview.alt = 'Existing ' + slot + ' artwork'; }
    else preview.removeAttribute('src');
  }
  function syncForms() {
    const entry = currentItem();
    el('gm-side-title').textContent = entry ? entry.label : 'Choose a title to begin';
    if (entry) {
      el('gm-meta-title').value = entry.title;
      el('gm-meta-overview').value = entry.overview;
      el('gm-meta-year').value = entry.year;
      el('gm-meta-runtime').value = entry.runtime;
      el('gm-meta-year-field').hidden = entry.kind === 'episode';
      const chosenPremium = form.querySelector('input[name="studio_premium"][value="' + entry.premium + '"]');
      if (chosenPremium) chosenPremium.checked = true;
      const isDraft = entry.status === 'draft';
      el('gm-meta-readonly').hidden = isDraft;
      el('gm-artwork-readonly').hidden = isDraft;
      el('gm-save-details').disabled = !isDraft;
      el('gm-save-artwork').disabled = !isDraft;
      const accessDraft = isDraft && (entry.kind === 'movie' || entry.seriesStatus === 'draft');
      el('gm-save-access').disabled = !accessDraft;
      el('gm-access-readonly').hidden = accessDraft;
      el('gm-access-series-note').hidden = entry.kind !== 'episode';
      const slot = el('gm-artwork-slot');
      slot.replaceChildren();
      if (entry.kind === 'episode') slot.add(new Option('Episode still (16:9)', 'still'));
      else { slot.add(new Option('Movie poster (2:3)', 'poster')); slot.add(new Option('Movie backdrop (16:9)', 'backdrop')); }
    }
    syncExistingArtwork();
  }
  function setUploadStatus(status) {
    ingestStatus = status;
    el('gm-side-status').textContent = displayStatus(status);
    el('gm-finish-status').textContent = displayStatus(status);
  }
  function enablePostUpload() {
    steps.forEach(b => { b.disabled = false; });
    el('gm-upload-finish').hidden = false;
    el('gm-upload-next').hidden = false;
    submit.hidden = true;
    el('gm-side-resume-wrap').hidden = false;
    el('gm-side-resume').href = form.dataset.uploadBase + '?upload=' + encodeURIComponent(ingestId);
  }
  function setProgress(value) {
    el('gm-upload-progress-wrap').hidden = false;
    const p = Math.max(0,Math.min(100,value));
    el('gm-upload-progress-fill').style.width = p + '%';
    el('gm-upload-percent').textContent = p + '%';
    el('gm-upload-progress').setAttribute('aria-valuenow', String(p));
    el('gm-upload-progress-text').textContent = p === 100 ? 'Saving your video…' : 'Uploading video…';
  }
  function busy(value) {
    submit.disabled = value;
    target.disabled = value;
    search.disabled = value;
    videoFile.disabled = value;
    types.forEach(t => { t.disabled = value; });
    cancel.hidden = !value;
  }
  function firstError(data, fallback) {
    if (data?.errors) {
      const first = Object.values(data.errors).flat()[0];
      if (first) return String(first);
    }
    return typeof data?.message === 'string' ? data.message : fallback;
  }
  async function requestJson(path, method, fields, isArtwork = false) {
    const opts = {method, credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':token}};
    if (isArtwork) opts.body = fields;
    else {opts.headers['Content-Type']='application/json';opts.body = JSON.stringify(fields);}
    const response = await fetch(path, opts);
    let body = {};
    try { body = await response.json(); } catch { /* show generic error */ }
    if (!response.ok || body.ok !== true) throw new Error(firstError(body,'Save failed. Check your connection and retry.'));
    return body;
  }
  function urlFor(suffix) { return '/upload-studio/' + encodeURIComponent(ingestId) + '/' + suffix; }

  types.forEach(type => type.addEventListener('change',() => {
    if (ingestId) return;
    search.value = '';
    filterTitles();
  }));
  search.addEventListener('input',filterTitles);
  target.addEventListener('change',syncForms);
  filterTitles();
  for (const event of ['dragenter','dragover']) {
    drop.addEventListener(event,e => {e.preventDefault();drop.classList.add('dragging');});
  }
  drop.addEventListener('dragleave',() => drop.classList.remove('dragging'));
  drop.addEventListener('drop',e => {
    e.preventDefault(); drop.classList.remove('dragging');
    if (ingestId || videoFile.disabled || !e.dataTransfer?.files?.length) return;
    try {
      const transfer = new DataTransfer();
      transfer.items.add(e.dataTransfer.files[0]);
      videoFile.files = transfer.files;
      videoFile.dispatchEvent(new Event('change'));
    } catch { message('gm-upload-feedback','Use Browse to choose a video.','error'); }
  });
  videoFile.addEventListener('change',() => {
    const f = videoFile.files?.[0];
    el('gm-upload-file-label').textContent = f?.name || 'Select or drop your video';
    el('gm-side-file').textContent = f?.name || 'No file selected';
    el('gm-upload-file-meta').textContent = f ? (f.size / 1048576).toFixed(1) + ' MB · ' + limitsLabel : limitsLabel;
    if (f?.size > maxBytes) message('gm-upload-feedback','This video exceeds your server upload limit.','error');
    else if (f?.size === 0) message('gm-upload-feedback','This video is empty.','error');
    else clearMessage('gm-upload-feedback');
  });
  cancel.addEventListener('click',() => xhr?.abort());
  form.addEventListener('submit',e => {
    e.preventDefault();
    if (xhr || ingestId) return;
    const file = videoFile.files?.[0];
    if (!currentItem()) return message('gm-upload-feedback','Choose an existing movie or episode.','error');
    if (!file) return message('gm-upload-feedback','Select a video file first.','error');
    if (!/\.(mp4|mov|mkv|webm)$/i.test(file.name)) return message('gm-upload-feedback','Supported videos: MP4, MOV, MKV, WebM.','error');
    if (!file.size || file.size > maxBytes) return message('gm-upload-feedback','Choose a non-empty video within your server limit.','error');
    if (!token) return message('gm-upload-feedback','Session expired. Refresh and retry.','error');
    // Collect multipart fields before disabling controls; disabled fields are omitted.
    const payload = new FormData(form);
    if (payload.get('type') !== currentType() || payload.get('title_id') !== target.value)
      return message('gm-upload-feedback','Unable to prepare the upload. Refresh and retry.','error');
    xhr = new XMLHttpRequest();
    xhr.open('POST',form.action,true);
    xhr.withCredentials = true;
    xhr.timeout = 900000;
    xhr.setRequestHeader('Accept','application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN',token);
    xhr.upload.addEventListener('progress',event => { if (event.lengthComputable) setProgress(Math.round(event.loaded/event.total*100)); });
    xhr.onload = () => {
      let data = {};
      try { data = JSON.parse(xhr.responseText); } catch {}
      if (xhr.status === 201 && data.ok === true && Number(data.upload_id) > 0) {
        ingestId = String(data.upload_id);
        setProgress(100);
        el('gm-upload-progress-text').textContent = 'Upload complete';
        setUploadStatus('quarantined');
        message('gm-upload-feedback','Video uploaded successfully. You can finish now or add optional details.','ok');
        enablePostUpload();
      } else {
        let reason = firstError(data,'The upload failed. Try again.');
        if (xhr.status === 413) reason = 'The server rejected the file because it is too large.';
        if (xhr.status === 419) reason = 'Your session expired. Refresh and retry.';
        message('gm-upload-feedback',reason,'error');
      }
      busy(false);
      if (ingestId) {
        target.disabled = true;
        search.disabled = true;
        videoFile.disabled = true;
        types.forEach(t => { t.disabled = true; });
      }
      xhr = null;
    };
    xhr.onerror = () => { message('gm-upload-feedback','Connection lost. Please retry.','error');busy(false);xhr=null; };
    xhr.ontimeout = () => { message('gm-upload-feedback','Upload timed out. Please retry.','error');busy(false);xhr=null; };
    xhr.onabort = () => { message('gm-upload-feedback','Upload cancelled.','error');busy(false);xhr=null; };
    busy(true);
    clearMessage('gm-upload-feedback');
    setProgress(0);
    xhr.send(payload);
  });
  el('gm-upload-next').addEventListener('click',() => selectStep(2));
  steps.forEach(b => b.addEventListener('click',() => {if (!b.disabled) selectStep(Number(b.dataset.step));}));
  document.querySelectorAll('[data-prev],[data-next]').forEach(b => b.addEventListener('click',() => selectStep(Number(b.dataset.prev || b.dataset.next))));
  el('gm-save-details').addEventListener('click',async() => {
    if (!ingestId) return;
    const button = el('gm-save-details');
    button.disabled = true;
    try {
      const data = await requestJson(urlFor('details'),'PATCH',{
        title: el('gm-meta-title').value.trim(),
        overview: el('gm-meta-overview').value,
        runtime_minutes: el('gm-meta-runtime').value || null,
        release_year: currentType() === 'movie' ? (el('gm-meta-year').value || null) : null
      });
      const entry = currentItem();
      if(entry){entry.title = data.title;entry.overview = el('gm-meta-overview').value;entry.runtime=el('gm-meta-runtime').value;entry.year=el('gm-meta-year').value;entry.label= data.title;}
      el('gm-side-title').textContent = data.title;
      message('gm-meta-feedback',data.message,'ok');
    } catch(error) { message('gm-meta-feedback',error.message,'error'); }
    button.disabled = !currentItem() || currentItem().status !== 'draft';
  });
  el('gm-artwork-slot').addEventListener('change',syncExistingArtwork);
  el('gm-artwork-file').addEventListener('change',() => {
    const file = el('gm-artwork-file').files?.[0];
    if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
    previewObjectUrl = file ? URL.createObjectURL(file) : null;
    const preview = el('gm-artwork-preview');
    preview.hidden = !file;
    if (file) preview.src = previewObjectUrl;
    else preview.removeAttribute('src');
    clearMessage('gm-artwork-feedback');
  });
  el('gm-save-artwork').addEventListener('click', async() => {
    const file = el('gm-artwork-file').files?.[0];
    if (!file) return message('gm-artwork-feedback','Choose an artwork image.','error');
    if (!/\.(jpg|jpeg|png|webp)$/i.test(file.name) || file.size > 8*1048576 || !file.size)
      return message('gm-artwork-feedback','Use a non-empty JPG, PNG or WebP image up to 8 MB.','error');
    const fields = new FormData();
    fields.append('slot',el('gm-artwork-slot').value);
    fields.append('artwork',file);
    const button=el('gm-save-artwork');
    button.disabled=true;
    try {
      const result=await requestJson(urlFor('artwork'),'POST',fields,true);
      const entry=currentItem();
      if (entry && result.artwork_url) entry.artwork[el('gm-artwork-slot').value] = result.artwork_url;
      syncExistingArtwork();
      message('gm-artwork-feedback',result.message,'ok');
    } catch(error) { message('gm-artwork-feedback',error.message,'error'); }
    button.disabled=!currentItem() || currentItem().status !== 'draft';
  });
  el('gm-save-access').addEventListener('click',async() => {
    const selected=form.querySelector('input[name="studio_premium"]:checked')?.value;
    if (selected === undefined) return;
    const button=el('gm-save-access');
    button.disabled=true;
    try {
      const result=await requestJson(urlFor('access'),'PATCH',{is_premium:selected === '1'});
      const entry=currentItem();if(entry)entry.premium=result.is_premium ? '1' : '0';
      message('gm-access-feedback',result.message,'ok');
    } catch(error) { message('gm-access-feedback',error.message,'error'); }
    const entry=currentItem();
    button.disabled=!entry || entry.status !== 'draft' || (entry.kind === 'episode' && entry.seriesStatus !== 'draft');
  });
  async function refreshStatus() {
    if (!ingestId) return;
    try {
      const response=await fetch(urlFor('status'),{headers:{'Accept':'application/json'},credentials:'same-origin'});
      const data=await response.json();
      if(!response.ok || data.ok !== true)throw new Error(firstError(data,'Unable to check upload status.'));
      setUploadStatus(data.status);
      clearMessage('gm-finish-feedback');
    } catch(error) {message('gm-finish-feedback',error.message,'error');}
  }
  el('gm-refresh-status').addEventListener('click',refreshStatus);
  if (ingestId && (resumeType === 'movie' || resumeType === 'episode')) {
    const radio=types.find(t => t.value===resumeType);if(radio)radio.checked=true;
    filterTitles();
    if(![...target.options].some(o => o.value===resumeTarget)){
      message('gm-upload-feedback','This upload belongs to a catalog title outside the current title list. Use Media Library to inspect it.','error');
    } else {
      target.value=resumeTarget;syncForms();setUploadStatus('quarantined');enablePostUpload();
      submit.hidden=true;videoFile.disabled=true;target.disabled=true;search.disabled=true;types.forEach(t=>t.disabled=true);
      drop.hidden=true;
      message('gm-upload-feedback','This video is already saved privately. Continue editing optional catalog details or finish.','ok');
    }
    refreshStatus();
  }
  window.addEventListener('beforeunload',() => {if(previewObjectUrl)URL.revokeObjectURL(previewObjectUrl);});
})();
</script>
</body>
</html>
