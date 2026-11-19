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
body{margin:0;background:#090b11;color:#f4f6fb;min-height:100vh}
[hidden]{display:none!important}
a{color:inherit;text-decoration:none}
button,input,textarea{font:inherit}
button{cursor:pointer}
.topbar{background:#11141d;border-bottom:1px solid #ffffff16}
.topbar-inner{max-width:1150px;min-height:72px;margin:auto;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}
.brand{font-size:23px;letter-spacing:-.055em;font-weight:900}
.brand em{font-style:normal;color:#f12856}
.nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:end}
.nav a,.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #343b4e;border-radius:10px;background:#161c29;color:#e5eaf4;padding:11px 15px;font-size:12px;font-weight:750;min-height:42px}
.nav a:hover,.btn:hover{border-color:#dd3b63;color:white}
.main{max-width:1150px;margin:0 auto;padding:42px 22px 76px}
h1{font-size:clamp(32px,4vw,43px);margin:8px 0 8px;letter-spacing:-.05em}
.intro{color:#aab3c5;margin:0 0 29px;line-height:1.6;font-size:14px}
.columns{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(260px,.85fr);gap:22px;align-items:start}
.card{border:1px solid #2a3040;background:#111520;border-radius:18px;overflow:hidden}
.content{padding:28px}
h2{margin:0 0 6px;letter-spacing:-.035em;font-size:21px}
.subtitle{font-size:13px;color:#9daabd;line-height:1.6;margin:0 0 25px}
.label{display:block;font-size:13px;font-weight:730;margin:0 0 8px}
.field{margin-bottom:22px}
.control{background:#0b0f19;border:1px solid #353d50;border-radius:11px;color:white;padding:12px 13px;min-height:47px;outline:0;width:100%}
.control:focus-visible{border-color:#f04772;box-shadow:0 0 0 3px #f0477227}
.control::placeholder{color:#7d8a9e}
textarea.control{min-height:85px;resize:vertical;line-height:1.5}
.help{display:block;color:#97a4b8;font-size:12px;margin:7px 0 0;line-height:1.55}
.drop{display:flex;position:relative;align-items:center;justify-content:center;flex-direction:column;gap:8px;text-align:center;min-height:160px;background:#0c1321;border:2px dashed #3b465e;border-radius:14px;padding:20px;cursor:pointer}
.drop:hover,.drop:focus-within,.drop.drag{border-color:#f0446c;background:#1b1b2b}
.drop input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.drop strong{font-size:14px;overflow-wrap:anywhere}
.drop small{color:#a2afc1}
.upload-icon{display:grid;place-items:center;font-size:25px;color:#ff7698;background:#f12f5723;border-radius:12px;width:49px;height:49px}
.permission{display:flex;gap:11px;align-items:flex-start;border:1px solid #343f51;padding:15px;border-radius:11px;background:#101a28;line-height:1.6;font-size:12px;color:#ced7e6;margin:25px 0 18px;cursor:pointer}
.permission input{margin-top:4px;flex:none;accent-color:#ef2c59}
.actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.primary{background:#ed2453;border-color:#ed2453;color:white}
.primary:hover{background:#fa3d68}
button:disabled{opacity:.5;cursor:not-allowed}
.error{color:#ffbbca;background:#3b1b28;border:1px solid #924158;border-radius:10px;padding:11px 13px;font-size:13px;line-height:1.5;margin-top:14px}
.progress{height:8px;background:#293345;border-radius:100px;overflow:hidden;margin-top:15px}
.progress span{display:block;background:linear-gradient(90deg,#fc8257,#f12757);height:100%;width:0;transition:width .18s}
.progress-label{font-size:12px;color:#b7c4d6;margin-top:6px}
.side{padding:23px}
.side h3{margin:0 0 18px;font-size:15px}
.side-item{border-bottom:1px solid #ffffff13;padding:14px 0}
.side-item:first-of-type{padding-top:0}
.side-item:last-of-type{border:0}
.side-item b{font-size:13px}
.side-item p{font-size:12px;color:#a4b1c7;line-height:1.6;margin:5px 0 0}
.status{padding:24px;margin-top:20px}
.status h3{margin:0 0 8px;font-size:18px}
.status .chip{display:inline-flex;border:1px solid #485269;border-radius:99px;padding:7px 12px;font-size:12px;color:#d8e1f0;margin:10px 0 18px}
.status p{font-size:13px;line-height:1.6;color:#b9c5d8}
.status .actions{margin-top:10px}
.upload-types{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 22px}
.upload-type{display:grid;gap:6px;padding:16px 18px}
.upload-type span{font-size:13px;color:#b9c5d8;line-height:1.5}
.upload-type:hover,.upload-type[aria-current]{border-color:#ef4168}
.underline{text-decoration:underline;text-underline-offset:3px}
@media(max-width:540px){.upload-types{grid-template-columns:1fr}.topbar-inner{flex-wrap:wrap}}
@media(max-width:810px){.columns{grid-template-columns:1fr}.main{padding:25px 15px 58px}.topbar-inner{padding:12px 16px}.content{padding:21px}.nav a{padding:9px 10px}}
@media(prefers-reduced-motion:reduce){.progress span{transition:none}}
</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner">
  <a href="{{ route('admin.dashboard') }}" class="brand"><em>GO</em>MOVIE</a>
  <nav class="nav" aria-label="Upload links">
    <a href="{{ route('admin.site-settings.edit') }}#uploads">Upload settings</a>
    <a href="{{ route('admin.media.index') }}">Media Library</a>
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
  </nav>
</div></header>
<main class="main">
  <h1>Upload Studio</h1>
  <p class="intro">Choose what you're uploading.</p>
  <nav class="upload-types" aria-label="Upload type">
    <a class="card upload-type" href="{{ route('upload-studio.advanced', ['type' => 'movie']) }}">
      <strong>Movie</strong><span>Select an existing movie title.</span>
    </a>
    <a class="card upload-type" href="{{ route('upload-studio.advanced', ['type' => 'episode']) }}">
      <strong>TV Show</strong><span>Select an episode of an existing show.</span>
    </a>
    <a class="card upload-type" href="{{ route('upload-studio.index') }}" aria-current="page">
      <strong>Normal Video</strong><span>Upload directly for public viewing.</span>
    </a>
  </nav>
  <div class="columns">
    <div>
      <section class="card content" id="normal-video" aria-labelledby="normal-video-heading" @if($ingestId) hidden @endif>
        <h2 id="normal-video-heading">Upload a normal video</h2>
        <p class="subtitle">Your watch page is public as soon as the upload succeeds. Anyone can watch once processing finishes.</p>
        <form id="quick-upload-form" method="post" enctype="multipart/form-data"
              action="{{ route('upload-studio.quick') }}" data-max="{{ $maxUploadBytes }}"
              data-status-template="{{ url('/upload-studio/__id__/status') }}"
              data-resume="{{ $ingestId ?? '' }}" data-resume-access="{{ $resumeAccess ?? '' }}">
          @csrf
          <div class="field">
            <label class="label" for="video-title">Title (optional)</label>
            <input class="control" name="title" id="video-title" maxlength="255"
                   placeholder="Defaults to the file name" value="{{ old('title') }}">
          </div>
          <div class="field">
            <label class="label" for="video-file">Video file</label>
            <label class="drop" id="video-drop">
              <span class="upload-icon" aria-hidden="true">↑</span>
              <strong id="video-name">Choose or drop a video</strong>
              <small id="video-size">MP4, MOV, MKV, WebM · Maximum {{ number_format($maxUploadBytes / 1048576, 1) }} MB</small>
              <input id="video-file" type="file" name="video"
                     accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" required>
            </label>
          </div>
          <div class="field">
            <label class="label" for="video-description">Description <span class="help" style="display:inline">— optional</span></label>
            <textarea class="control" name="description" id="video-description"
                      placeholder="A short description (you can add this later)">{{ old('description') }}</textarea>
          </div>
          @unless($autoReady)
            <p class="help">Turn on FFmpeg transcoding, HLS playback, and automatic publication in <a class="underline" href="{{ route('admin.site-settings.edit') }}#media">Video processing</a> to enable normal video uploads.</p>
          @endunless
          <label class="permission">
            <input type="checkbox" name="rights_confirmed" value="1" required>
            <span id="permission-text">I created this video or have permission to publish it worldwide online.</span>
          </label>
          <div class="actions">
            <button type="submit" class="btn primary" id="video-submit" @disabled(!$autoReady)>Upload &amp; publish</button>
            <span class="help">Secure scanning and conversion run automatically in the background.</span>
          </div>
          <div id="upload-progress" hidden>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progress-fill"></span></div>
            <div class="progress-label" id="progress-text">Uploading 0%</div>
          </div>
          <p class="error" id="upload-error" role="alert" hidden></p>
          @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
        </form>
      </section>
      <section class="card status" id="upload-result" aria-live="polite" hidden>
        <h3>Video uploaded</h3>
        <p id="result-message">Checking your upload...</p>
        <span class="chip" id="result-status">Checking status</span>
        <div class="actions">
          <a href="#" id="result-watch" class="btn primary" hidden>Open public watch page</a>
          <a href="#" id="result-edit" class="btn" hidden>Edit video details</a>
          <button class="btn" id="result-refresh" type="button">Check status</button>
          <a href="{{ route('upload-studio.index') }}" class="btn">Upload another</a>
          <a href="{{ route('admin.media.index') }}" class="btn">Media Library</a>
        </div>
        <p class="error" id="result-error" role="alert" hidden></p>
      </section>
    </div>
    <aside class="card">
      <div class="side">
        <h3>Movie and TV uploads</h3>
        <div class="side-item"><b>Movie</b><p>Save the movie title in Movies, then select it in Upload Studio.</p>
          <p><a class="underline" href="{{ route('admin.catalog.create', ['type' => 'movies']) }}">Create movie title</a></p></div>
        <div class="side-item"><b>TV Show</b><p>Save the show in TV Series, add a season and episode, then select that episode in Upload Studio.</p>
          <p><a class="underline" href="{{ route('admin.catalog.create', ['type' => 'series']) }}">Create TV show title</a></p></div>
      </div>
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
  const max=Number(form.dataset.max||0);
  const statusTemplate=form.dataset.statusTemplate;
  const resume=Number(form.dataset.resume||0);
  let ingest=resume>0?resume:0;
  const resumeAccess=form.dataset.resumeAccess||'';
  let timer=null;
  let xhr=null;

  function error(text){get('upload-error').textContent=text;get('upload-error').hidden=false;}
  function notify(text){window.GomovieFeedback?.show(text,'success');}
  function displayStatus(value){
    return ({quarantined:'Uploaded · Processing queued',approved:'Security scan complete',
      processing:'Converting video',encoded:'Encoding complete',
      rejected:'Upload rejected',public:'Public and playable'})[value]||'Saved privately';
  }
  function setFile(){
    const selected=file.files?.[0];
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
    if(!e.dataTransfer?.files?.length) return;
    try{const dt=new DataTransfer();dt.items.add(e.dataTransfer.files[0]);file.files=dt.files;setFile();}
    catch{error('Use the file chooser to select a video.');}
  });

  function setResult(text){
    get('upload-result').hidden=false;
    if(text)get('result-message').textContent=text;
  }
  async function refresh(){
    if (!ingest) return;
    try {
      const url=statusTemplate.replace('__id__',encodeURIComponent(String(ingest)));
      const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
      const data=await response.json();
      if(!response.ok||data.ok!==true)throw new Error('Unable to check processing. Try again.');
      get('result-status').textContent=data.public?'Public and playable':displayStatus(data.status);
      const watch=get('result-watch');
      watch.hidden=!(data.watch_page_public&&data.watch_url);
      if(!watch.hidden)watch.href=data.watch_url;
      const edit=get('result-edit');
      edit.hidden=!data.catalog_edit_url;
      if(!edit.hidden)edit.href=data.catalog_edit_url;
      if(data.public)setResult('Your video is ready to watch.');
      else if(data.watch_page_public&&!data.publication_error&&data.status!=='rejected'){
        setResult('Your watch page is public. The video is being prepared for playback.');
      }
      else if(data.watch_page_public)setResult('Your watch page is public. Playback is unavailable.');
      else if(resumeAccess==='private')setResult('Saved privately. Only administrators can access the original.');
      else if(resumeAccess==='paid')setResult('Saved as a private paid-video draft. Checkout is not enabled.');
      else if(data.status==='quarantined')setResult('Uploaded. Waiting for automatic processing.');
      else if(data.status==='processing'||data.status==='approved')setResult('Gomovie is converting your video.');
      if(data.public||data.status==='rejected'||data.publication_error){
        if(timer){clearInterval(timer);timer=null;}
      }
      const detail=get('result-error');
      detail.hidden=!data.publication_error&&data.status!=='rejected';
      if(!detail.hidden){
        detail.textContent=data.publication_error==='active_free_distribution_rights_required'
          ? 'Playback is unavailable because this title lacks active distribution rights.'
          : 'Processing could not complete. Open Media Library to review this upload.';
      }
    } catch(err){
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
  form.addEventListener('submit',e=>{
    if(!window.XMLHttpRequest)return;
    e.preventDefault();
    if(xhr||ingest)return;
    const selected=file.files?.[0];
    if(!form.reportValidity())return;
    if(!selected||!selected.size||selected.size>max){error('Choose a non-empty video within the upload limit.');return;}
    if(!/\.(mp4|mov|mkv|webm)$/i.test(selected.name)){error('Please choose an MP4, MOV, MKV or WebM file.');return;}
    const payload=new FormData(form);
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
      if(!ev.lengthComputable)return;
      const pct=Math.min(100,Math.round(100*ev.loaded/ev.total));
      get('progress-fill').style.width=pct+'%';
      get('upload-progress').querySelector('[role="progressbar"]').setAttribute('aria-valuenow',String(pct));
      get('progress-text').textContent='Uploading '+pct+'%';
    };
    xhr.onload=()=>{
      let data={};
      try{data=JSON.parse(xhr.responseText)}catch{}
      if(xhr.status===201&&data.ok===true&&Number(data.upload_id)>0){
        ingest=Number(data.upload_id);
        get('progress-fill').style.width='100%';
        get('progress-text').textContent='Upload complete';
        setResult(data.message||'Video uploaded.');
        get('result-status').textContent='Uploaded';
        get('normal-video').hidden=true;
        const watch=get('result-watch');
        watch.href=data.watch_url;
        watch.hidden=false;
        notify(data.message||'Video uploaded successfully.');
        window.history.replaceState({},'',window.location.pathname+'?ingest='+encodeURIComponent(String(ingest)));
        startPoll();
        get('upload-result').scrollIntoView({behavior:'smooth',block:'center'});
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
  if(ingest){form.hidden=true;setResult('Checking your saved upload...');startPoll();}
  window.addEventListener('pagehide',()=>{if(timer)clearInterval(timer);});
})();
</script>
</body>
</html>
