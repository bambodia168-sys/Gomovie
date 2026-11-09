<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0c0d12">
<meta name="robots" content="noindex,nofollow">
<title>Upload Studio — Gomovie</title>
<style>
:root{color-scheme:dark;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:radial-gradient(ellipse 60% 42% at 50% 0%,#25151f 0%,transparent 80%),#0b0c10;color:#f8f8fa}
a{color:inherit;text-decoration:none}
button,input,select{font:inherit}
button{cursor:pointer}
[hidden]{display:none!important}
.us-header{border-bottom:1px solid #ffffff16;background:#101116b8}
.us-header-inner{max-width:1000px;min-height:76px;margin:auto;padding:0 25px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.us-brand{font-size:23px;font-weight:900;letter-spacing:-.065em;white-space:nowrap}
.us-brand span{color:#f23855}
.us-header-right{display:flex;gap:14px;align-items:center}
.us-back{display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:8px 13px;border:1px solid #ffffff2b;border-radius:10px;color:#d2d0da;font-size:12px;font-weight:650}
.us-back:hover{border-color:#ff687c;color:white}
.us-page{max-width:850px;margin:0 auto;padding:clamp(30px,5vw,62px) 22px 80px}
.us-kicker{display:block;color:#ff7284;text-transform:uppercase;letter-spacing:.18em;font-size:11px;font-weight:850}
.us-heading{font-size:clamp(32px,5vw,43px);letter-spacing:-.055em;line-height:1.12;margin:10px 0 12px;font-weight:880}
.us-subtitle{color:#a9a8b6;margin:0;font-size:14px;line-height:1.65}
.us-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:30px 0 23px}
.us-step{display:flex;align-items:center;gap:10px;color:#b5b2c0;font-size:12px;font-weight:700;min-width:0}
.us-step span{width:28px;height:28px;display:grid;place-items:center;flex:none;border:1px solid #ffffff30;border-radius:9px;font-size:12px;color:#f3eef2}
.us-step:first-child span{border-color:#f34264;background:#f3426426;color:#ff718a}
.us-card{border:1px solid #ffffff1e;border-radius:23px;background:linear-gradient(155deg,#181920,#111218 85%);box-shadow:0 28px 70px #0004;overflow:hidden}
.us-section{padding:clamp(22px,4vw,32px)}
.us-section+.us-section{border-top:1px solid #ffffff18}
.us-section-heading{display:flex;align-items:center;gap:11px;margin:0 0 19px}
.us-number{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;border:1px solid #f3426450;background:#f342641a;color:#ff7c91;font-size:12px;font-weight:850;flex:none}
.us-section-heading h2{margin:0;font-size:17px;letter-spacing:-.025em}
.us-type-row{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:22px}
.us-choice{position:relative}
.us-choice input{position:absolute;opacity:0;width:1px;height:1px}
.us-choice span{display:inline-flex;align-items:center;justify-content:center;min-width:116px;min-height:42px;padding:9px 16px;border:1px solid #ffffff24;border-radius:11px;background:#101117;color:#b5b3c2;font-size:13px;font-weight:750;transition:border-color .15s,background .15s}
.us-choice input:checked+span{background:#ec345322;border-color:#f34b67;color:white}
.us-choice input:focus-visible+span{outline:2px solid #f67a8c;outline-offset:3px}
.us-label{display:block;color:#e3e1e9;font-size:12px;font-weight:750;margin-bottom:9px}
.us-input{width:100%;height:46px;padding:10px 13px;border:1px solid #ffffff30;background:#0d0e13;border-radius:11px;color:#f4f3f7;outline:0;font-size:13px}
.us-input:focus{border-color:#f75c73;box-shadow:0 0 0 3px #f14c6229}
.us-input::placeholder{color:#898795}
.us-input option{background:#101116;color:#fff}
.us-field+.us-field{margin-top:15px}
.us-hint{color:#a6a4b2;font-size:12px;line-height:1.5;margin:9px 0 0}
.us-drop{position:relative;min-height:194px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;padding:26px;border:2px dashed #ffffff30;border-radius:15px;background:linear-gradient(145deg,#201920,#15151e);cursor:pointer;text-align:center;transition:border-color .15s,background .15s}
.us-drop:hover,.us-drop.is-dragging,.us-drop:focus-within{border-color:#f25b76;background:#261921}
.us-drop input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.us-drop input:disabled{cursor:not-allowed}
.us-upload-icon{display:grid;place-items:center;width:45px;height:45px;border-radius:14px;background:#e8395421;color:#ff8197}
.us-upload-icon svg{width:24px;height:24px}
.us-file-name{font-size:15px;font-weight:760;max-width:100%;overflow-wrap:anywhere}
.us-file-hint{font-size:12px;color:#aba6b5}
.us-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px}
.us-primary{border:0;border-radius:11px;min-height:48px;padding:11px 22px;background:#e83151;color:#fff;font-size:13px;font-weight:800}
.us-primary:hover{background:#f74765}
.us-primary:disabled{opacity:.55;cursor:not-allowed}
.us-secondary{border:1px solid #ffffff30;border-radius:11px;min-height:42px;padding:10px 16px;background:transparent;color:#eee;font-size:12px;font-weight:750}
.us-secondary:hover{background:#ffffff0e}
.us-status{border-radius:11px;padding:12px 14px;background:#23202a;border:1px solid #ffffff1c;font-size:13px;line-height:1.55;margin:16px 0 0;overflow-wrap:anywhere}
.us-status.is-error{background:#381923;border-color:#fd4c6270;color:#ffb8c2}
.us-status.is-success{background:#123325;border-color:#4cce9870;color:#b2f8d4}
.us-progress-head{display:flex;justify-content:space-between;gap:15px;color:#c8c4d0;font-size:12px;margin-bottom:9px}
.us-progress{height:8px;width:100%;background:#ffffff18;border-radius:20px;overflow:hidden}
.us-progress-fill{width:0;height:100%;background:linear-gradient(90deg,#fa9162,#ef355d);transition:width .15s}
.us-progress-wrap{margin:18px 0 0}
.us-success-actions{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:16px}
.us-review-link{color:#ff9ba9;font-size:13px;font-weight:750;text-decoration:underline;text-underline-offset:3px}
.us-footnote{color:#9896a5;font-size:12px;text-align:center;margin:18px 0 0;line-height:1.6}
@media(max-width:580px){.us-header-inner{padding:0 17px;min-height:67px}.us-page{padding:32px 14px 60px}.us-card{border-radius:18px}.us-section{padding:21px 18px}.us-step{font-size:11px;gap:6px}.us-step span{width:25px;height:25px}.us-choice{flex:1}.us-choice span{width:100%;min-width:0}.us-back{font-size:11px;padding:8px 10px}.us-brand{font-size:20px}}
@media(prefers-reduced-motion:reduce){.us-progress-fill,.us-choice span,.us-drop{transition:none}}
</style>
</head>
<body>
<header class="us-header">
  <div class="us-header-inner">
    <a class="us-brand" href="{{ route('admin.dashboard') }}" aria-label="Gomovie dashboard"><span>GO</span>MOVIE</a>
    <div class="us-header-right"><a class="us-back" href="{{ route('admin.dashboard') }}">← Back to dashboard</a></div>
  </div>
</header>
<main class="us-page">
  <span class="us-kicker">Gomovie Studio</span>
  <h1 class="us-heading">Upload a video</h1>
  <p class="us-subtitle">Choose a title, add your video, and upload.</p>
  <div class="us-steps" aria-label="Upload steps">
    <div class="us-step"><span>1</span> Choose title</div>
    <div class="us-step"><span>2</span> Select file</div>
    <div class="us-step"><span>3</span> Upload</div>
  </div>
  <form id="gm-upload-studio-form" action="{{ route('upload-studio.upload') }}" method="POST" enctype="multipart/form-data"
        data-max-bytes="{{ $maxUploadBytes }}" novalidate>
    @csrf
    <div class="us-card">
      <section class="us-section" aria-labelledby="us-title-step">
        <div class="us-section-heading"><span class="us-number">1</span><h2 id="us-title-step">Choose a title</h2></div>
        <div class="us-type-row" role="group" aria-label="Video type">
          <label class="us-choice"><input type="radio" name="type" value="movie" checked><span>Movie</span></label>
          <label class="us-choice"><input type="radio" name="type" value="episode"><span>TV episode</span></label>
        </div>
        <div class="us-field">
          <label class="us-label" for="gm-upload-search">Find a movie or episode</label>
          <input id="gm-upload-search" class="us-input" type="search" placeholder="Search titles" autocomplete="off">
        </div>
        <div class="us-field">
          <label class="us-label" for="gm-upload-target">Select title</label>
          <select id="gm-upload-target" class="us-input" name="title_id" required>
            <option value="">Choose a title</option>
            @foreach($movies as $movie)
              <option data-kind="movie" value="{{ $movie->id }}">{{ $movie->title }}</option>
            @endforeach
            @foreach($episodes as $episode)
              <option data-kind="episode" value="{{ $episode->id }}">{{ $episode->season?->series?->title ?? 'Series' }} · S{{ $episode->season?->season_number ?? '?' }} E{{ $episode->episode_number }} — {{ $episode->title }}</option>
            @endforeach
          </select>
          <p class="us-hint" id="us-title-hint" hidden>No matching titles. Try another search.</p>
        </div>
      </section>
      <section class="us-section" aria-labelledby="us-file-step">
        <div class="us-section-heading"><span class="us-number">2</span><h2 id="us-file-step">Select your video</h2></div>
        <label class="us-drop" id="gm-upload-drop">
          <span class="us-upload-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m0 0L7 8m5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
          </span>
          <span id="gm-upload-file-label" class="us-file-name">Drag a video here or click to browse</span>
          <span id="gm-upload-file-meta" class="us-file-hint">MP4, MOV, MKV or WebM · Maximum {{ number_format($maxUploadBytes / 1048576, 1) }} MB</span>
          <input id="gm-upload-file" name="video" type="file" accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" required aria-label="Choose video file">
        </label>
      </section>
      <section class="us-section" aria-labelledby="us-send-step">
        <div class="us-section-heading"><span class="us-number">3</span><h2 id="us-send-step">Upload</h2></div>
        <div class="us-progress-wrap" id="gm-upload-progress-wrap" hidden>
          <div class="us-progress-head"><span id="gm-upload-progress-text">Uploading…</span><strong id="gm-upload-percent">0%</strong></div>
          <div class="us-progress" id="gm-upload-progress" role="progressbar" aria-label="Video upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="us-progress-fill" id="gm-upload-progress-fill"></div></div>
        </div>
        <p id="gm-upload-feedback" class="us-status" role="status" aria-live="polite" hidden></p>
        <div class="us-actions">
          <button id="gm-upload-submit" type="submit" class="us-primary">Upload video →</button>
          <button id="gm-upload-cancel" type="button" class="us-secondary" hidden>Cancel upload</button>
        </div>
        <div id="gm-upload-success-actions" class="us-success-actions" hidden>
          <a id="gm-upload-review-link" class="us-review-link" href="#" hidden>Review this upload →</a>
          <button type="button" class="us-secondary" id="gm-upload-another">Upload another video</button>
        </div>
      </section>
    </div>
  </form>
  <p class="us-footnote">Uploads are stored privately until reviewed. They are never published automatically.</p>
</main>
<script>
(() => {
  'use strict';
  const form = document.getElementById('gm-upload-studio-form');
  if (!form) return;
  const types = [...form.querySelectorAll('input[name="type"]')];
  const target = document.getElementById('gm-upload-target');
  const search = document.getElementById('gm-upload-search');
  const titleHint = document.getElementById('us-title-hint');
  const input = document.getElementById('gm-upload-file');
  const drop = document.getElementById('gm-upload-drop');
  const fileLabel = document.getElementById('gm-upload-file-label');
  const fileMeta = document.getElementById('gm-upload-file-meta');
  const submit = document.getElementById('gm-upload-submit');
  const cancel = document.getElementById('gm-upload-cancel');
  const feedback = document.getElementById('gm-upload-feedback');
  const progressWrap = document.getElementById('gm-upload-progress-wrap');
  const progressText = document.getElementById('gm-upload-progress-text');
  const progress = document.getElementById('gm-upload-progress');
  const progressFill = document.getElementById('gm-upload-progress-fill');
  const percent = document.getElementById('gm-upload-percent');
  const successActions = document.getElementById('gm-upload-success-actions');
  const reviewLink = document.getElementById('gm-upload-review-link');
  const another = document.getElementById('gm-upload-another');
  const maxBytes = Number(form.dataset.maxBytes);
  const originals = [...target.options].filter(option => option.value).map(option => ({
    value: option.value, label: option.textContent, kind: option.dataset.kind
  }));
  const placeholderMeta = fileMeta.textContent;
  let xhr = null;

  function selectedType() { return form.querySelector('input[name="type"]:checked')?.value || ''; }
  function filterTitles() {
    const prior = target.value;
    const kind = selectedType();
    const query = search.value.trim().toLocaleLowerCase();
    const choices = originals.filter(option => option.kind === kind && option.label.toLocaleLowerCase().includes(query));
    target.replaceChildren(new Option('Choose a title', ''));
    for (const item of choices) target.add(new Option(item.label, item.value));
    if (choices.some(item => item.value === prior)) target.value = prior;
    titleHint.hidden = choices.length > 0;
  }
  function show(message, state = '') {
    feedback.textContent = message;
    feedback.className = 'us-status' + (state ? ' is-' + state : '');
    feedback.hidden = false;
  }
  function busy(isBusy) {
    submit.disabled = isBusy;
    input.disabled = isBusy;
    search.disabled = isBusy;
    target.disabled = isBusy;
    types.forEach(item => { item.disabled = isBusy; });
    cancel.hidden = !isBusy;
  }
  function setProgress(value) {
    progressWrap.hidden = false;
    const p = Math.min(100, Math.max(0, value));
    progressFill.style.width = p + '%';
    progress.setAttribute('aria-valuenow', String(p));
    percent.textContent = p + '%';
    progressText.textContent = p === 100 ? 'Finishing upload…' : 'Uploading…';
  }
  function clearSuccess() {
    successActions.hidden = true;
    reviewLink.hidden = true;
    reviewLink.removeAttribute('href');
  }
  types.forEach(item => item.addEventListener('change', () => {
    target.value = '';
    search.value = '';
    filterTitles();
  }));
  search.addEventListener('input', filterTitles);
  filterTitles();
  for (const eventName of ['dragenter', 'dragover']) {
    drop.addEventListener(eventName, event => { event.preventDefault(); drop.classList.add('is-dragging'); });
  }
  drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
  drop.addEventListener('drop', event => {
    event.preventDefault();
    drop.classList.remove('is-dragging');
    if (input.disabled || !event.dataTransfer?.files?.length) return;
    try {
      const transfer = new DataTransfer();
      transfer.items.add(event.dataTransfer.files[0]);
      input.files = transfer.files;
      input.dispatchEvent(new Event('change'));
    } catch {
      show('Please use Browse to select your video.', 'error');
    }
  });
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    fileLabel.textContent = file ? file.name : 'Drag a video here or click to browse';
    fileMeta.textContent = file ? (file.size / 1048576).toFixed(1) + ' MB · ' + placeholderMeta : placeholderMeta;
    if (file && file.size > maxBytes) show('This file is too large for the current server upload limit. Choose a smaller video.', 'error');
    else if (file && file.size === 0) show('This video file is empty. Choose another file.', 'error');
    else if (feedback.classList.contains('is-error')) feedback.hidden = true;
  });
  cancel.addEventListener('click', () => xhr?.abort());
  another.addEventListener('click', () => {
    if (xhr) return;
    form.reset();
    search.value = '';
    input.value = '';
    fileLabel.textContent = 'Drag a video here or click to browse';
    fileMeta.textContent = placeholderMeta;
    progressWrap.hidden = true;
    feedback.hidden = true;
    clearSuccess();
    submit.hidden = false;
    filterTitles();
    window.scrollTo({top: 0, behavior: 'smooth'});
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (xhr) return;
    const file = input.files?.[0];
    const kind = selectedType();
    if (!['movie', 'episode'].includes(kind)) return show('Choose Movie or TV episode.', 'error');
    if (!target.value) return show('Choose the movie or episode for this video.', 'error');
    if (!file) return show('Choose a video file first.', 'error');
    if (!/\.(mp4|mov|mkv|webm)$/i.test(file.name)) return show('Choose an MP4, MOV, MKV or WebM file.', 'error');
    if (!file.size) return show('This video file is empty.', 'error');
    if (file.size > maxBytes) return show('This file exceeds the current upload limit of ' + (maxBytes / 1048576).toFixed(1) + ' MB.', 'error');
    const token = form.querySelector('input[name="_token"]')?.value;
    if (!token) return show('Your session expired. Refresh the page and try again.', 'error');

    // IMPORTANT: FormData MUST be collected while the controls are enabled.
    // Disabled inputs are omitted, causing "The type field is required."
    const payload = new FormData(form);
    const submittedFile = payload.get('video');
    if (payload.get('type') !== kind || payload.get('title_id') !== target.value || !(submittedFile instanceof File)
      || submittedFile.size !== file.size || submittedFile.name !== file.name) {
      return show('Unable to prepare the upload. Please refresh the page.', 'error');
    }
    clearSuccess();
    feedback.hidden = true;
    submit.hidden = false;
    setProgress(0);
    xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.withCredentials = true;
    xhr.timeout = 900000;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', token);
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) setProgress(Math.round(e.loaded / e.total * 100));
    });
    xhr.onload = () => {
      let data = {};
      try { data = JSON.parse(xhr.responseText); } catch { /* use status-specific error */ }
      if (xhr.status === 201 && data.ok === true) {
        setProgress(100);
        progressText.textContent = 'Upload complete';
        show('Video uploaded successfully. It is private and waiting for review.', 'success');
        const reviewUrl = data.review_url;
        if (typeof reviewUrl === 'string') {
          try {
            const safe = new URL(reviewUrl, window.location.origin);
            if (safe.origin === window.location.origin && safe.pathname.startsWith('/dashboard/upload-studio/')) {
              reviewLink.href = safe.href;
              reviewLink.hidden = false;
            }
          } catch { /* Do not expose untrusted URL */ }
        }
        successActions.hidden = false;
        submit.hidden = true;
      } else {
        let message = data?.errors ? Object.values(data.errors).flat()[0] : data?.message;
        if (xhr.status === 413) message = 'The server rejected this file because it exceeds its upload limit.';
        if (xhr.status === 419) message = 'Your session expired. Refresh this page and upload again.';
        if (xhr.status === 429) message = 'Too many upload attempts. Please try again shortly.';
        if (!message) message = 'Upload failed. Please try again.';
        show(String(message), 'error');
      }
      busy(false);
      xhr = null;
    };
    xhr.onerror = () => { show('Connection lost. Check your network and try again.', 'error'); busy(false); xhr = null; };
    xhr.ontimeout = () => { show('Upload timed out. Try a smaller file or check your connection.', 'error'); busy(false); xhr = null; };
    xhr.onabort = () => { show('Upload canceled.', 'error'); busy(false); xhr = null; };
    busy(true);
    show('Uploading your video. Keep this page open.');
    xhr.send(payload);
  });
})();
</script>
</body>
</html>
