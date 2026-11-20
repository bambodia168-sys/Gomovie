<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Upload Studio — Gomovie</title>
@include('partials.feedback')
<script>
try {
  if (localStorage.getItem('gomovie.theme') === 'light') document.documentElement.dataset.uploadTheme = 'light';
} catch (_) {}
</script>
<style>
:root {
  color-scheme: dark;
  font-family: Inter, ui-sans-serif, system-ui, sans-serif;
  --canvas: #0a0b10;
  --surface: #12141d;
  --field: #0e1018;
  --subtle: #191d29;
  --border: #303645;
  --text: #f6f7fc;
  --muted: #aeb7ca;
  --brand: #ff6289;
  --tint: #281924;
  --tint-border: #714052;
  --action: #cf184b;
  --action-hover: #b81040;
  --success: #78e0b5;
  --success-bg: #142922;
  --alert: #ffb1c4;
  --alert-bg: #321c28;
  --hero: #1b111b;
  --shadow: #02030852;
}
:root[data-upload-theme="light"] {
  color-scheme: light;
  --canvas: #f6f5f8;
  --surface: #fff;
  --field: #faf9fc;
  --subtle: #f4f1f7;
  --border: #d9d3e1;
  --text: #231a2b;
  --muted: #60556c;
  --brand: #a71645;
  --tint: #fff0f5;
  --tint-border: #df9ab2;
  --success: #08724c;
  --success-bg: #e4f7ed;
  --alert: #922343;
  --alert-bg: #fff0f4;
  --hero: #f7e7ef;
  --shadow: #3e244c0c;
}
*, *::before, *::after { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  background: radial-gradient(ellipse 780px 325px at 11% 58px, var(--hero), transparent), var(--canvas);
  color: var(--text);
}
[hidden] { display: none !important; }
a { color: inherit; text-decoration: none; }
button, input, textarea { font: inherit; }
button { cursor: pointer; }
button:disabled { opacity: .58; cursor: not-allowed; }
:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
svg { display: block; flex: none; }
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
.wrap { width: min(1160px, calc(100% - 48px)); margin-inline: auto; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); }
.topbar-inner {
  min-height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}
.brand { font-size: 23px; letter-spacing: -.065em; font-weight: 900; }
.brand em { font-style: normal; color: var(--brand); }
.nav, .actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.nav { justify-content: flex-end; gap: 4px; }
.nav a, .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  border-radius: 9px;
  padding: 9px 13px;
  font-size: 13px;
  font-weight: 700;
}
.nav a { color: var(--muted); }
.nav a:hover { color: var(--text); background: var(--subtle); }
.btn { color: var(--text); border: 1px solid var(--border); background: var(--subtle); }
.btn:hover:not(:disabled) { border-color: var(--brand); }
.primary { background: var(--action); border-color: var(--action); color: white; }
.primary:hover:not(:disabled) { background: var(--action-hover); border-color: var(--action-hover); }
.main { padding-block: 38px 62px; }
h1, h2, h3, p { margin: 0; }
h1 { font-size: clamp(33px, 4vw, 48px); line-height: 1.04; letter-spacing: -.055em; }
h2 { font-size: 22px; font-weight: 760; letter-spacing: -.035em; }
h3 { font-size: 15px; letter-spacing: -.015em; }
.upload-types {
  display: inline-grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  margin-block: 19px 24px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--field);
}
.upload-type {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 41px;
  padding-inline: 16px;
  border: 1px solid transparent;
  border-radius: 8px;
  color: var(--muted);
  font-size: 13px;
  font-weight: 720;
}
.upload-type:hover { color: var(--text); background: var(--subtle); }
.upload-type[aria-current] { color: var(--brand); border-color: var(--tint-border); background: var(--tint); }
.columns { display: grid; grid-template-columns: minmax(0, 1fr) minmax(238px, 294px); gap: 22px; align-items: start; }
.card { min-width: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 17px; box-shadow: 0 12px 35px var(--shadow); }
.steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-bottom: 1px solid var(--border);
  list-style: none;
  margin: 0;
  padding: 19px 24px 17px;
}
.step {
  --marker: 32px;
  position: relative;
  display: grid;
  justify-items: center;
  align-content: start;
  gap: 8px;
  text-align: center;
  font-size: 12px;
  line-height: 1.3;
  font-weight: 670;
  color: var(--muted);
}
.step:not(:last-child)::after {
  content: "";
  position: absolute;
  top: calc(var(--marker) / 2);
  inset-inline-start: calc(50% + var(--marker) / 2 + 7px);
  width: calc(100% - var(--marker) - 14px);
  height: 1px;
  background: var(--border);
}
.step[data-state="complete"]::after { background: var(--success); }
.step-marker {
  display: grid;
  place-items: center;
  height: var(--marker);
  width: var(--marker);
  border: 1px solid var(--border);
  border-radius: 50%;
  background: var(--field);
  font-variant-numeric: tabular-nums;
}
.step-check, .step-warning, .step-completed-text { display: none; }
.step[aria-current] { color: var(--text); font-weight: 760; }
.step[data-state="active"] .step-marker { background: var(--tint); border-color: var(--brand); color: var(--brand); }
.step[data-state="complete"] .step-marker { background: var(--success-bg); border-color: var(--success); color: var(--success); }
.step[data-state="error"] .step-marker { background: var(--alert-bg); border-color: var(--alert); color: var(--alert); }
.step:is([data-state="complete"], [data-state="error"]) .step-number { display: none; }
.step[data-state="complete"] .step-check, .step[data-state="complete"] .step-completed-text { display: block; }
.step[data-state="error"] .step-warning { display: block; }
.content, .status { padding: 25px 27px; scroll-margin-top: 16px; }
.subtitle, .side p, .status p {
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}
.subtitle { margin: 6px 0 18px; }
.label { display: block; font-weight: 720; font-size: 13px; margin-bottom: 8px; }
.optional { color: var(--muted); font-weight: 500; margin-inline-start: 4px; }
.drop {
  display: grid;
  position: relative;
  justify-items: center;
  align-content: center;
  gap: 8px;
  text-align: center;
  min-height: 154px;
  border: 1px dashed var(--border);
  border-radius: 12px;
  background: var(--field);
  padding: 17px 15px;
  cursor: pointer;
}
.drop:hover, .drop:focus-within, .drop.drag, .drop[data-selected] { background: var(--tint); border-color: var(--tint-border); }
.drop:focus-within { outline: 2px solid var(--brand); outline-offset: 3px; }
.drop input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.drop input:disabled { cursor: default; }
.drop strong { font-size: 15px; overflow-wrap: anywhere; min-width: 0; }
.drop small, .help { color: var(--muted); font-size: 12px; line-height: 1.5; }
.upload-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--tint-border);
  border-radius: 11px;
  background: var(--tint);
  color: var(--brand);
}
.upload-icon svg { width: 21px; height: 21px; }
.details { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr); gap: 15px; margin-top: 20px; }
.control {
  width: 100%;
  min-height: 45px;
  padding: 11px 12px;
  background: var(--field);
  border: 1px solid var(--border);
  border-radius: 9px;
  color: var(--text);
  font-size: 14px;
  line-height: 1.45;
}
.control::placeholder { color: var(--muted); }
textarea.control { min-height: 69px; resize: vertical; display: block; }
.permission {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 18px 0 0;
  padding-block: 13px;
  border-block-start: 1px solid var(--border);
  color: var(--text);
  font-size: 13px;
  line-height: 1.5;
  cursor: pointer;
}
.permission input { margin: 3px 0 0; flex: none; width: 16px; height: 16px; accent-color: var(--action); }
.error, .settings-note {
  margin-top: 14px;
  padding: 11px 13px;
  border-radius: 9px;
  line-height: 1.5;
  background: var(--alert-bg);
  color: var(--alert);
  border: 1px solid var(--tint-border);
  font-size: 13px;
}
.underline { text-decoration: underline; text-underline-offset: 3px; }
.transfer, [data-transfer-controls] { margin-top: 14px; }
[data-transfer-controls] .help { margin: 0 0 8px; }
.progress { background: var(--subtle); height: 6px; border-radius: 99px; overflow: hidden; }
.progress span { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--brand); transition: width .18s; }
.progress-label { text-align: end; margin: 5px 0 0; font-size: 12px; font-variant-numeric: tabular-nums; color: var(--muted); }
.side { display: grid; gap: 16px; }
.side-card { padding: 19px; }
.side-title { display: flex; align-items: center; gap: 9px; margin-bottom: 9px; }
.side-title svg { color: var(--brand); }
.catalog-links { display: grid; gap: 4px; margin: 10px -7px -5px; }
.catalog-links a { display: flex; align-items: center; gap: 9px; padding: 9px 7px; border-radius: 8px; font-size: 13px; }
.catalog-links a svg { color: var(--muted); width: 16px; height: 16px; }
.catalog-links a:hover { background: var(--subtle); }
.status p { margin-top: 8px; }
.status .actions { margin-top: 21px; }
.status .error { color: var(--alert); }
.chip {
  display: inline-flex;
  margin-bottom: 10px;
  min-height: 26px;
  align-items: center;
  padding: 4px 9px;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--subtle);
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
}
.status[data-state="ready"] .chip { color: var(--success); background: var(--success-bg); border-color: var(--success); }
.status[data-state="error"] .chip { color: var(--alert); background: var(--alert-bg); border-color: var(--alert); }
@media (max-width: 810px) {
  .wrap { width: calc(100% - 32px); }
  .main { padding-top: 29px; }
  .columns { grid-template-columns: minmax(0, 1fr); gap: 16px; }
  .side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
  .topbar-inner { min-height: 58px; }
  .nav a { min-width: 38px; padding: 8px; }
  .nav a span { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
  .upload-types { display: grid; margin-bottom: 16px; }
  .upload-type { gap: 5px; padding-inline: 4px; font-size: 12px; }
  .upload-type svg { width: 14px; height: 14px; }
  .steps { padding: 16px 9px; }
  .step { font-size: 11px; }
  .content, .status { padding: 20px 17px; }
  .details, .side { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) { .progress span { transition: none; } }
</style>
@vite('resources/js/resumable-upload.js')
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a href="{{ route('admin.dashboard') }}" class="brand" aria-label="GoMovie Dashboard"><em>GO</em>MOVIE</a>
    <nav class="nav" aria-label="Upload links">
      <a href="{{ route('admin.site-settings.edit') }}#uploads">@include('partials.studio-icon', ['name' => 'settings']) <span>Upload settings</span></a>
      <a href="{{ route('admin.media.index') }}">@include('partials.studio-icon', ['name' => 'media']) <span>Media Library</span></a>
      <a href="{{ route('admin.dashboard') }}">@include('partials.studio-icon', ['name' => 'grid']) <span>Dashboard</span></a>
    </nav>
  </div>
</header>

<main class="wrap main">
  <h1>Upload Studio</h1>
  <nav class="upload-types" aria-label="Upload type">
    <a class="upload-type" href="{{ route('upload-studio.index') }}" aria-current="page">
      @include('partials.studio-icon', ['name' => 'upload']) Normal Video
    </a>
    <a class="upload-type" href="{{ route('upload-studio.advanced', ['type' => 'movie']) }}">
      @include('partials.studio-icon', ['name' => 'film']) Movie
    </a>
    <a class="upload-type" href="{{ route('upload-studio.advanced', ['type' => 'episode']) }}">
      @include('partials.studio-icon', ['name' => 'series']) TV Show
    </a>
  </nav>

  <div class="columns">
    <div class="card">
      @php($currentStep = $ingestId ? 2 : 1)
      <ol class="steps" aria-label="Normal video upload stages" id="normal-video-steps">
        @foreach([1 => 'Upload', 2 => 'Processing', 3 => 'Ready to watch'] as $step => $label)
          <li class="step" data-step="{{ $step }}" data-state="{{ $step < $currentStep ? 'complete' : ($step === $currentStep ? 'active' : 'pending') }}"
              @if($step === $currentStep) aria-current="step" @endif>
            <span class="step-marker" aria-hidden="true">
              <span class="step-number">{{ $step }}</span>
              <span class="step-check">@include('partials.studio-icon', ['name' => 'check'])</span>
              <span class="step-warning">!</span>
            </span>
            <span>{{ $label }}<span class="sr-only step-completed-text"> completed</span></span>
          </li>
        @endforeach
      </ol>

      <section class="content" id="normal-video" aria-labelledby="normal-video-heading" @if($ingestId) hidden @endif>
        <h2 id="normal-video-heading">Upload a normal video</h2>
        <p class="subtitle">Choose a video and add any details you want viewers to see.</p>
        <form id="quick-upload-form"
              data-transfer-url="{{ route('upload-studio.transfers.store') }}"
              data-transfer-owner="{{ auth()->id() }}" data-transfer-workflow="quick"
              data-direct-upload-bytes="{{ $directUploadBytes }}" method="post" enctype="multipart/form-data"
              action="{{ route('upload-studio.quick') }}" data-max="{{ $maxUploadBytes }}"
              data-status-template="{{ url('/upload-studio/__id__/status') }}"
              data-resume="{{ $ingestId ?? '' }}" data-resume-access="{{ $resumeAccess ?? '' }}">
          @csrf
          <label class="drop" id="video-drop" for="video-file">
            <span class="upload-icon" aria-hidden="true">@include('partials.studio-icon', ['name' => 'upload'])</span>
            <strong id="video-name">Choose or drop a video</strong>
            <small id="video-size">MP4, MOV, MKV, WebM · Maximum {{ number_format($maxUploadBytes / 1048576, 1) }} MB</small>
            <input id="video-file" type="file" name="video" aria-label="Video file" aria-describedby="video-size"
                   accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" required>
          </label>
          <div class="details">
            <div>
              <label class="label" for="video-title">Title <span class="optional">(optional)</span></label>
              <input class="control" name="title" id="video-title" maxlength="255"
                     placeholder="Defaults to the file name" value="{{ old('title') }}">
            </div>
            <div>
              <label class="label" for="video-description">Description <span class="optional">(optional)</span></label>
              <textarea class="control" name="description" id="video-description"
                        placeholder="Tell viewers about this video">{{ old('description') }}</textarea>
            </div>
          </div>
          @unless($autoReady)
            <p class="settings-note">Enable FFmpeg transcoding, HLS playback, and automatic publication in <a class="underline" href="{{ route('admin.site-settings.edit') }}#media">Video processing</a> to upload a normal video.</p>
          @endunless
          <label class="permission">
            <input type="checkbox" name="rights_confirmed" value="1" required>
            <span>I created this video or have permission to publish it worldwide online.</span>
          </label>
          <div class="actions">
            <button type="submit" class="btn primary" id="video-submit" @disabled(!$autoReady)>Upload &amp; publish</button>
          </div>
          <div class="transfer" id="upload-progress" hidden>
            <div class="progress" role="progressbar" aria-label="Video transfer" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progress-fill"></span></div>
            <p class="progress-label" id="progress-text">0% uploaded</p>
          </div>
          @include('admin.partials.resumable-upload-controls', ['noticeClass' => 'help', 'actionsClass' => 'actions'])
          <p class="error" id="upload-error" role="alert" hidden></p>
          @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
        </form>
      </section>

      <section class="status" id="upload-result" aria-labelledby="result-heading" @unless($ingestId) hidden @endunless>
        <span class="chip" id="result-status">Checking status</span>
        <h2 id="result-heading" tabindex="-1">Video uploaded</h2>
        <p id="result-message" role="status">Checking your upload…</p>
        <div class="actions">
          <a href="#" id="result-watch" class="btn" hidden>Open public watch page @include('partials.studio-icon', ['name' => 'external'])</a>
          <a href="#" id="result-edit" class="btn" hidden>Edit video details</a>
          <button class="btn" id="result-refresh" type="button">Check status</button>
          <a href="{{ route('upload-studio.index') }}" class="btn">Upload another</a>
          <a href="{{ route('admin.media.index') }}" class="btn">Media Library</a>
        </div>
        <p class="error" id="result-error" role="alert" hidden></p>
      </section>
    </div>

    <aside class="side">
      <section class="card side-card">
        <div class="side-title">
          @include('partials.studio-icon', ['name' => 'eye'])
          <h3>Public playback</h3>
        </div>
        <p>Your watch page goes public after upload. GoMovie scans and converts the video in the background, then makes it playable.</p>
      </section>
      <section class="card side-card">
        <h3>Catalog entries</h3>
        <p>Movies and TV episodes need a catalog entry before you upload their video.</p>
        <div class="catalog-links">
          <a href="{{ route('admin.catalog.create', ['type' => 'movies']) }}">@include('partials.studio-icon', ['name' => 'plus']) Create movie title</a>
          <a href="{{ route('admin.catalog.create', ['type' => 'series']) }}">@include('partials.studio-icon', ['name' => 'plus']) Create TV show title</a>
        </div>
      </section>
    </aside>
  </div>
</main>
<script>
(() => {
  'use strict';
  const form=document.getElementById('quick-upload-form');
  if (!form) return;
  const get=id=>document.getElementById(id);
  const file=get('video-file');
  const drop=get('video-drop');
  const submit=get('video-submit');
  const steps=Array.from(get('normal-video-steps').querySelectorAll('[data-step]'));
  const result=get('upload-result');
  const resultHeading=get('result-heading');
  const max=Number(form.dataset.max||0);
  const statusTemplate=form.dataset.statusTemplate;
  const resume=Number(form.dataset.resume||0);
  let ingest=resume>0?resume:0;
  const resumeAccess=form.dataset.resumeAccess||'';
  let timer=null;
  let xhr=null;
  let transfer=null;

  function error(text){get('upload-error').textContent=text;get('upload-error').hidden=false;}
  function notify(text){window.GomovieFeedback?.show(text,'success');}
  function displayStatus(value){
    return ({quarantined:'Uploaded · Processing queued',approved:'Security scan complete',
      processing:'Converting video',encoded:'Encoding complete',
      rejected:'Upload rejected',public:'Public and playable'})[value]||'Saved privately';
  }
  function setFile(){
    const selected=file.files?.[0];
    drop.toggleAttribute('data-selected',!!selected);
    get('video-name').textContent=selected?.name||'Choose or drop a video';
    get('video-size').textContent=selected
      ? (selected.size/1048576).toFixed(1)+' MB'
      : 'MP4, MOV, MKV, WebM · Maximum '+(max/1048576).toFixed(1)+' MB';
    get('upload-error').hidden=true;
  }
  file.addEventListener('change',setFile);
  for(const ev of ['dragenter','dragover']) drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('drag');});
  drop.addEventListener('dragleave',()=>drop.classList.remove('drag'));
  drop.addEventListener('drop',e=>{
    e.preventDefault();drop.classList.remove('drag');
    if(file.disabled||!e.dataTransfer?.files?.length) return;
    try{const dt=new DataTransfer();dt.items.add(e.dataTransfer.files[0]);file.files=dt.files;setFile();}
    catch{error('Use the file chooser to select a video.');}
  });

  function setResult(state,text){
    result.hidden=false;
    result.dataset.state=state;
    resultHeading.textContent=({
      processing:'Processing your video',
      ready:'Your video is ready',
      error:'Processing needs attention',
      saved:'Video saved',
    })[state];
    get('result-message').textContent=text;

    const current=state==='ready'?3:2;
    for(const step of steps){
      const number=Number(step.dataset.step);
      const isCurrent=number===current;
      if(isCurrent)step.setAttribute('aria-current','step');
      else step.removeAttribute('aria-current');
      if(number<current||state==='ready')step.dataset.state='complete';
      else if(!isCurrent)step.dataset.state='pending';
      else step.dataset.state=state==='processing'?'active':state;
    }
    get('result-watch').classList.toggle('primary',state==='ready');
  }
  async function refresh(){
    if(!ingest)return;
    try{
      const url=statusTemplate.replace('__id__',encodeURIComponent(String(ingest)));
      const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
      const data=await response.json();
      if(!response.ok||data.ok!==true)throw new Error('Unable to check processing. Try again.');
      const failed=!!data.publication_error||data.status==='rejected';
      const saved=!data.watch_page_public&&!data.public&&(
        !data.automatic_publication_enabled||['private','paid'].includes(resumeAccess)
      );
      const watch=get('result-watch');
      watch.hidden=!(data.watch_page_public&&data.watch_url);
      if(!watch.hidden)watch.href=data.watch_url;
      const edit=get('result-edit');
      edit.hidden=!data.catalog_edit_url;
      if(!edit.hidden)edit.href=data.catalog_edit_url;
      get('result-status').textContent=data.public?'Public and playable':failed?'Processing failed':
        saved?'Saved privately':displayStatus(data.status);

      if(data.public){
        setResult('ready','Your video is public and ready to watch.');
      }else if(failed){
        setResult('error',data.watch_page_public
          ? 'The watch page is public. Playback is currently unavailable.'
          : 'Playback is currently unavailable.');
      }else if(saved){
        setResult('saved',resumeAccess==='paid'?'Saved as a private paid-video draft. Checkout is not enabled.'
          :'This upload is saved in Media Library.');
      }else{
        setResult('processing',data.watch_page_public
          ? 'Your watch page is public. Playback starts when processing finishes.'
          : 'Your upload is being prepared for playback.');
      }

      if((data.public||failed||saved)&&timer){clearInterval(timer);timer=null;}
      const detail=get('result-error');
      detail.hidden=!failed||data.public;
      if(!detail.hidden){
        detail.textContent=data.publication_error==='active_free_distribution_rights_required'
          ? 'This title needs active distribution rights before it can play.'
          : 'Open Media Library to review this upload.';
      }
    }catch(err){
      get('result-error').textContent=err.message;
      get('result-error').hidden=false;
    }
  }
  function startPoll(){
    if(timer) clearInterval(timer);
    refresh();
    let count=0;
    timer=setInterval(()=>{if(++count>=60){clearInterval(timer);timer=null;return;}refresh();},5000);
  }
  get('result-refresh').addEventListener('click',refresh);
  function setProgress(pct){
    get('upload-progress').hidden=pct===null;
    if(pct===null)return;
    get('progress-fill').style.width=pct+'%';
    get('upload-progress').querySelector('[role="progressbar"]').setAttribute('aria-valuenow',String(pct));
    get('progress-text').textContent=pct===100?'Saving your video…':pct+'% uploaded';
  }
  function completeUpload(data){
    ingest=Number(data.upload_id);
    get('progress-fill').style.width='100%';
    get('progress-text').textContent='Upload complete';
    setResult('processing',data.message||'Your upload is being prepared for playback.');
    get('result-status').textContent='Uploaded';
    get('normal-video').hidden=true;
    const watch=get('result-watch');
    watch.href=data.watch_url;
    watch.hidden=false;
    notify(data.message||'Video uploaded successfully.');
    window.history.replaceState({},'',window.location.pathname+'?ingest='+encodeURIComponent(String(ingest)));
    startPoll();
    resultHeading.focus({preventScroll:true});
    result.scrollIntoView({
      behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',
      block:'nearest',
    });
  }
  if(!ingest){
    document.addEventListener('DOMContentLoaded',()=>{
      transfer=window.GomovieResumableUpload?.attach(form,{
        submit, fields:['title','description'], progress:setProgress,
        restore:fields=>{
          form.elements.title.value=fields.title||'';
          form.elements.description.value=fields.description||'';
        },
      });
    });
  }
  form.addEventListener('submit',async e=>{
    if(!window.XMLHttpRequest)return;
    e.preventDefault();
    if(xhr||ingest||transfer?.active)return;
    const selected=file.files?.[0];
    if(!form.reportValidity())return;
    if(!selected||!selected.size||selected.size>max){error('Choose a non-empty video within the upload limit.');return;}
    if(!/\.(mp4|mov|mkv|webm)$/i.test(selected.name)){error('Please choose an MP4, MOV, MKV or WebM file.');return;}
    const payload=new FormData(form);
    if(transfer?.shouldUse(selected)){
      submit.disabled=true;
      file.disabled=true;
      get('upload-error').hidden=true;
      try{
        const data=await transfer.send(selected,payload);
        if(data)completeUpload(data);
      }catch(err){
        error(err.message);
      }finally{
        submit.disabled=!!ingest;
        file.disabled=!!ingest;
      }
      return;
    }
    const token=form.querySelector('[name="_token"]')?.value;
    xhr=new XMLHttpRequest();
    xhr.open('POST',form.action,true);
    xhr.withCredentials=true;
    xhr.timeout=900000;
    xhr.setRequestHeader('Accept','application/json');
    if(token)xhr.setRequestHeader('X-CSRF-TOKEN',token);
    submit.disabled=true;get('upload-error').hidden=true;
    get('upload-progress').hidden=false;
    xhr.upload.onprogress=ev=>{
      if(ev.lengthComputable)setProgress(Math.round(100*ev.loaded/ev.total));
    };
    xhr.onload=()=>{
      let data={};
      try{data=JSON.parse(xhr.responseText)}catch{}
      if(xhr.status===201&&data.ok===true&&Number(data.upload_id)>0){
        completeUpload(data);
      }else{
        let msg=data.message||'Upload failed. Please try again.';
        if(data.errors){const messages=Object.values(data.errors).flat();if(messages.length)msg=messages[0];}
        if(xhr.status===413)msg='The file exceeds the server upload limit.';
        if(xhr.status===419)msg='Your session expired. Refresh and try again.';
        error(msg);
      }
      xhr=null;submit.disabled=!!ingest;
    };
    xhr.onerror=()=>{error('Connection interrupted. Check Media Library before retrying.');xhr=null;submit.disabled=false;};
    xhr.ontimeout=()=>{error('Upload timed out. Check Media Library before retrying.');xhr=null;submit.disabled=false;};
    xhr.send(payload);
  });
  if(ingest){setResult('processing','Checking your saved upload…');startPoll();}
  window.addEventListener('pagehide',()=>{if(timer)clearInterval(timer);});
})();
</script>
</body>
</html>
