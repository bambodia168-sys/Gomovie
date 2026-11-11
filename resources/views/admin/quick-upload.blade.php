<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0b0d14">
<title>Upload Video — Gomovie</title>
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
.eyebrow{text-transform:uppercase;font-size:11px;letter-spacing:.17em;color:#fa7999;font-weight:850}
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
.accesses{display:grid;gap:10px}
.access{display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:16px;background:#0e1320;border:1px solid #333d51;border-radius:12px}
.access:has(input:checked){border-color:#ed315d;background:#ed315d13}
.access input{accent-color:#f12c58;margin-top:4px}
.access b{font-size:13px}
.access small{display:block;margin-top:4px;color:#a4afc2;line-height:1.5;font-size:12px}
.access:has(input:disabled){opacity:.55}
.permission{display:flex;gap:11px;align-items:flex-start;border:1px solid #343f51;padding:15px;border-radius:11px;background:#101a28;line-height:1.6;font-size:12px;color:#ced7e6;margin:25px 0 18px;cursor:pointer}
.permission input{margin-top:4px;flex:none;accent-color:#ef2c59}
.actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.primary{background:#ed2453;border-color:#ed2453;color:white}
.primary:hover{background:#fa3d68}
button:disabled{opacity:.5;cursor:not-allowed}
.error{color:#ffbbca;background:#3b1b28;border:1px solid #924158;border-radius:10px;padding:11px 13px;font-size:13px;line-height:1.5;margin-top:14px}
.progress{height:8px;background:#293345;border-radius:100px;overflow:hidden;margin-top:15px}
.progress span{display:block;background:linear-gradient(90deg,#fc8257,#f12757);height:100%;width:0;transition:width .18s}
.progress-label{font-size:12px;color:#b7c4d6;margin-top:6px;line-height:1.55}
.transfer-details{margin:8px 0 0;color:#97a4b8;font-size:12px;line-height:1.5}
.drop.invalid{border-color:#f56383;background:#381927}
.upload-actions{margin-top:4px}
.upload-actions .btn{min-height:40px}
.upload-actions .btn:focus-visible,.drop:focus-within{outline:2px solid #ffafc4;outline-offset:2px}
.upload-actions .btn-secondary{background:transparent;border-color:#495169}
.upload-check{color:#ffbdcf;border-bottom:1px solid #ffbdcf55;font-size:12px;font-weight:750;text-underline-offset:3px}
.upload-check:hover{color:#ffe7ee}
.upload-privacy-note{margin:12px 0 0;padding:10px 12px;border:1px solid #ffffff1c;border-radius:11px;background:#ffffff05;color:#b7c3d4;font-size:12px;line-height:1.55}
@media(max-width:540px){.upload-actions .btn{flex:1}.upload-check{padding:8px 3px}}

.side{padding:23px}
.side h3{margin:0 0 18px;font-size:15px}
.side-item{border-bottom:1px solid #ffffff13;padding:14px 0}
.side-item:first-of-type{padding-top:0}
.side-item:last-of-type{border:0}
.side-item b{font-size:13px}
.side-item p{font-size:12px;color:#a4b1c7;line-height:1.6;margin:5px 0 0}
.notice{background:#0b1926;border-top:1px solid #ffffff15;padding:17px 23px;font-size:12px;color:#a8bbd0;line-height:1.7}
.status{padding:24px;margin-top:20px}
.status h3{margin:0 0 8px;font-size:18px}
.status .chip{display:inline-flex;border:1px solid #485269;border-radius:99px;padding:7px 12px;font-size:12px;color:#d8e1f0;margin:10px 0 18px}
.status p{font-size:13px;line-height:1.6;color:#b9c5d8}
.status .actions{margin-top:10px}
@media(max-width:810px){.columns{grid-template-columns:1fr}.main{padding:25px 15px 58px}.topbar-inner{padding:12px 16px}.content{padding:21px}.nav a{padding:9px 10px}}
@media(prefers-reduced-motion:reduce){.progress span{transition:none}}
</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner">
  <a href="{{ route('admin.dashboard') }}" class="brand"><em>GO</em>MOVIE</a>
  <nav class="nav" aria-label="Upload links">
    <a href="{{ route('admin.media.index') }}">Media Library</a>
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
  </nav>
</div></header>
<main class="main">
  <span class="eyebrow">Gomovie Studio</span>
  <h1>Upload a video</h1>
  <p class="intro">Give your video a title, select the file, then upload. Progress and cancellation are visible; media approval stays protected.</p>
  <div class="columns">
    <div>
      <section class="card content" aria-label="Quick upload">
        <h2>New video</h2>
        <p class="subtitle">Only the basics are needed. Add posters, subtitles, or extra information later if you want.</p>
        <form id="quick-upload-form" method="post" enctype="multipart/form-data"
              action="{{ route('upload-studio.quick') }}" data-max="{{ $maxUploadBytes }}"
              data-status-template="{{ url('/upload-studio/__id__/status') }}"
              data-resume="{{ $ingestId ?? '' }}" data-resume-access="{{ $resumeAccess ?? '' }}">
          @csrf
          <div class="field">
            <label class="label" for="video-title">Video title</label>
            <input class="control" name="title" id="video-title" maxlength="255" minlength="2"
                   placeholder="Example: My original short film" value="{{ old('title') }}" required>
          </div>
          <div class="field">
            <label class="label" for="video-file">Video file</label>
            <label class="drop" id="video-drop">
              <span class="upload-icon" aria-hidden="true">↑</span>
              <strong id="video-name">Choose or drop an MP4 video</strong>
              <small id="video-size">MP4, MOV, MKV, WebM · Maximum {{ number_format($maxUploadBytes / 1048576, 1) }} MB</small>
              <small>Drag and drop one video, or select a file. Your original stays private.</small>
              <input id="video-file" type="file" name="video"
                     accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" required>
            </label>
          </div>
          <div class="field">
            <label class="label" for="video-description">Description <span class="help" style="display:inline">— optional</span></label>
            <textarea class="control" name="description" id="video-description"
                      placeholder="A short description (you can add this later)">{{ old('description') }}</textarea>
          </div>
          <div class="field">
            <span class="label">Who can watch?</span>
            <div class="accesses" role="group" aria-label="Video visibility">
              <label class="access">
                <input type="radio" name="access" value="free" {{ $autoReady ? 'checked' : 'disabled' }}>
                <span><b>Free · Public</b><small>Available only on installations that explicitly enable reviewed media processing and public streaming. The video starts in private quarantine.</small></span>
              </label>
              <label class="access">
                <input type="radio" name="access" value="private" {{ $autoReady ? '' : 'checked' }}>
                <span><b>Private</b><small>Keep the original video in your private Media Library. No public playback.</small></span>
              </label>
              <label class="access">
                <input type="radio" name="access" value="paid">
                <span><b>Paid video · Draft</b><small>Prepare a paid listing, stored privately. Purchases are unavailable until checkout is configured and verified.</small></span>
              </label>
            </div>
            @unless($autoReady)
            <p class="help">Public processing is not configured on this installation. Private uploads are available now.</p>
            @endunless
          </div>
          <label class="permission">
            <input type="checkbox" name="rights_confirmed" value="1" required>
            <span id="permission-text">I created this video or have permission to publish it worldwide online.</span>
          </label>
          <div class="actions upload-actions">
            <button type="submit" class="btn primary" id="video-submit">Upload video →</button>
            <button type="button" class="btn btn-secondary" id="upload-cancel" hidden>Cancel transfer</button>
            <button type="button" class="btn btn-secondary" id="upload-retry" hidden>Retry upload</button>
            <a id="upload-review" class="upload-check" href="{{ route('admin.media.index') }}" hidden>Check Media Library first</a>
            <span class="help">Uploaded files start in private quarantine. Public processing depends on your server's safety configuration.</span>
          </div>
          <div id="upload-progress" hidden>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progress-fill"></span></div>
            <div class="progress-label" id="progress-text" role="status" aria-live="polite">Uploading 0%</div>
            <p class="transfer-details">A completed transfer is not a published video. Approval and secure processing remain separate.</p>
          </div>
          <p class="error" id="upload-error" role="alert" hidden></p>
          @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
        </form>
      </section>
      <section class="card status" id="upload-result" aria-live="polite" hidden>
        <h3>Video uploaded</h3>
        <p id="result-message">Checking your upload...</p>
        <span class="chip" id="result-status">Saved privately</span>
        <div class="actions">
          <a href="#" id="result-watch" class="btn primary" hidden>Watch public video</a>
          <button class="btn" id="result-refresh" type="button">Check status</button>
          <a href="{{ route('upload-studio.index') }}" class="btn">Upload another</a>
          <a href="{{ route('admin.media.index') }}" class="btn">Media Library</a>
        </div>
        <p class="error" id="result-error" role="alert" hidden></p>
      </section>
    </div>
    <aside class="card">
      <div class="side">
        <h3>That's all you need</h3>
        <div class="side-item"><b>1. Upload</b><p>Choose a title and original video file.</p></div>
        <div class="side-item"><b>2. Private safety checks</b><p>Your original stays in quarantine while security, rights, and processing requirements are checked.</p></div>
        <div class="side-item"><b>3. Publish only when eligible</b><p>Approved free titles can become playable when configured safety checks pass. Private and paid drafts remain private.</p></div>
      </div>
      <div class="notice">Only upload videos you own or are authorized to use. You can edit details later, and permissions can be revoked. Paid checkout is not active.
        <p style="margin:12px 0 0"><a href="{{ route('upload-studio.advanced') }}" style="text-decoration:underline">Advanced editor</a> for existing titles and optional artwork.</p>
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
  const radios=[...form.querySelectorAll('input[name="access"]')];
  const file=get('video-file');
  const drop=get('video-drop');
  const submit=get('video-submit');
  const cancel=get('upload-cancel');
  const retry=get('upload-retry');
  const review=get('upload-review');
  const max=Number(form.dataset.max||0);
  const statusTemplate=form.dataset.statusTemplate;
  const resume=Number(form.dataset.resume||0);
  let ingest=resume>0?resume:0;
  let requestedAccess=form.dataset.resumeAccess||'';
  let timer=null;
  let xhr=null;

  function permission(){
    const free=form.querySelector('input[name="access"]:checked')?.value==='free';
    get('permission-text').textContent=free
      ? 'I created this video or have permission to publish it worldwide online.'
      : 'I own this video or have permission to upload and store it.';
  }
  radios.forEach(r=>r.addEventListener('change',permission));
  permission();

  function error(text){get('upload-error').textContent=text;get('upload-error').hidden=false;}
  function notify(text){window.GomovieFeedback?.show(text,'success');}
  function displayStatus(value){
    return ({quarantined:'Private quarantine',approved:'Safety review recorded',
      processing:'Private processing',encoded:'Encoded (publication pending)',
      rejected:'Upload rejected',public:'Published and playable'})[value]||'Saved privately';
  }
  function setFile(){
    const selected=file.files?.[0];
    get('video-name').textContent=selected?.name||'Choose or drop an MP4 video';
    get('video-size').textContent=selected
      ? (selected.size/1048576).toFixed(1)+' MB'
      : 'MP4, MOV, MKV, WebM · Maximum '+(max/1048576).toFixed(1)+' MB';
    drop.classList.remove('invalid');
    get('upload-error').hidden=true;
    if(selected && (!selected.size || selected.size>max ||
        !/\.(mp4|mov|mkv|webm)$/i.test(selected.name))) {
      drop.classList.add('invalid');
      error(!selected.size || selected.size>max
        ? 'Choose a non-empty video within the upload limit.'
        : 'Please choose an MP4, MOV, MKV or WebM file.');
    }
    if(!xhr && !ingest && retry.hidden===false)retry.hidden=false;
  }
  file.addEventListener('change',setFile);
  for(const ev of ['dragenter','dragover']) drop.addEventListener(ev,e=>{
    e.preventDefault();
    if(!xhr)drop.classList.add('drag');
  });
  drop.addEventListener('dragleave',()=>drop.classList.remove('drag'));
  drop.addEventListener('drop',e=>{
    e.preventDefault();drop.classList.remove('drag');
    if(xhr || !e.dataTransfer?.files?.length) return;
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
      watch.hidden=!(data.public&&data.watch_url);
      if(data.public&&data.watch_url){watch.href=data.watch_url;setResult('Your video is ready to watch.');}
      else if(requestedAccess==='private')setResult('Saved privately. Only administrators can access the original.');
      else if(requestedAccess==='paid')setResult('Saved as a private paid-video draft. Checkout is not enabled.');
      else if(data.status==='quarantined')setResult('Your upload is in private quarantine. Safety, rights, or processing checks may still be pending.');
      else if(data.status==='processing'||data.status==='approved')setResult('Your video is under private review or processing. It is not public yet.');
      if(data.public||data.status==='rejected'||data.publication_error){
        if(timer){clearInterval(timer);timer=null;}
      }
      const detail=get('result-error');
      detail.hidden=!data.publication_error;
      if(data.publication_error){
        detail.textContent=data.publication_error==='active_free_distribution_rights_required'
          ? 'This upload is private because there is no publishing permission for this title.'
          : 'Processing could not complete. Your original remains private.';
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
  function transferBusy(busy){
    for(const control of form.querySelectorAll('input:not([type="hidden"]),textarea')) {
      control.disabled=busy || (control.name==='access' && control.value==='free' && !@json($autoReady));
    }
    submit.disabled=busy || !!ingest;
    cancel.hidden=!busy;
    drop.classList.toggle('is-busy',busy);
    if(busy){retry.hidden=true;review.hidden=true;}
  }
  function resetTransfer(){
    get('progress-fill').style.width='0%';
    get('upload-progress').querySelector('[role="progressbar"]').setAttribute('aria-valuenow','0');
    get('progress-text').textContent='Uploading 0%';
  }
  function retryableFailure(text){
    error(text);
    retry.hidden=false;
    review.hidden=true;
    get('progress-text').textContent='Not uploaded. Correct the issue and retry.';
  }
  function uncertainFailure(text){
    error(text+' The server may have already saved the upload. Check Media Library before starting another.');
    retry.hidden=true;
    review.hidden=false;
    get('progress-text').textContent='Upload outcome unconfirmed — check your library first.';
  }
  cancel.addEventListener('click',()=>{
    if(xhr && xhr.readyState!==XMLHttpRequest.DONE)xhr.abort();
  });
  retry.addEventListener('click',()=>{
    if(xhr||ingest)return;
    retry.hidden=true;
    get('upload-error').hidden=true;
    form.requestSubmit();
  });
  form.addEventListener('submit',e=>{
    if(!window.XMLHttpRequest)return;
    e.preventDefault();
    if(xhr||ingest)return;
    const selected=file.files?.[0];
    if(!form.reportValidity())return;
    if(!selected||!selected.size||selected.size>max){
      drop.classList.add('invalid');
      error('Choose a non-empty video within the upload limit.');
      return;
    }
    if(!/\.(mp4|mov|mkv|webm)$/i.test(selected.name)){
      drop.classList.add('invalid');
      error('Please choose an MP4, MOV, MKV or WebM file.');
      return;
    }
    const access=form.querySelector('input[name="access"]:checked')?.value;
    requestedAccess=access||'private';
    // Snapshot the multipart data BEFORE disabling the controls.
    const payload=new FormData(form);
    const token=form.querySelector('[name="_token"]')?.value;
    const request=new XMLHttpRequest();
    xhr=request;
    request.open('POST',form.action,true);
    request.withCredentials=true;
    request.timeout=900000;
    request.setRequestHeader('Accept','application/json');
    if(token)request.setRequestHeader('X-CSRF-TOKEN',token);
    get('upload-error').hidden=true;
    get('upload-progress').hidden=false;
    resetTransfer();
    transferBusy(true);
    request.upload.onprogress=event=>{
      if(xhr!==request||!event.lengthComputable)return;
      const pct=Math.min(100,Math.round(100*event.loaded/event.total));
      get('progress-fill').style.width=pct+'%';
      get('upload-progress').querySelector('[role="progressbar"]')
        .setAttribute('aria-valuenow',String(pct));
      get('progress-text').textContent=pct>=100
        ? 'File transferred. Waiting for a confirmed server receipt…'
        : 'Uploading '+pct+'%';
    };
    function finish(){
      if(xhr!==request)return;
      xhr=null;
      transferBusy(false);
    }
    request.onload=()=>{
      if(xhr!==request)return;
      let data={};
      try{data=JSON.parse(request.responseText)}catch{}
      if(request.status===201 && data.ok===true && Number.isSafeInteger(Number(data.upload_id))
          && Number(data.upload_id)>0){
        ingest=Number(data.upload_id);
        get('progress-fill').style.width='100%';
        get('upload-progress').querySelector('[role="progressbar"]').setAttribute('aria-valuenow','100');
        get('progress-text').textContent='Upload saved in private storage';
        setResult(data.message||'Video uploaded.');
        get('result-status').textContent='Private intake received';
        form.hidden=true;
        notify(data.message||'Video uploaded successfully.');
        window.history.replaceState({},'',window.location.pathname+'?ingest='+encodeURIComponent(String(ingest)));
        startPoll();
        get('upload-result').scrollIntoView({behavior:'smooth',block:'center'});
      }else{
        let msg=data.message||'Upload could not be completed.';
        if(data.errors){const messages=Object.values(data.errors).flat();if(messages.length)msg=messages[0];}
        if(request.status===413)msg='The file exceeds your server upload limit. Choose a smaller video.';
        if(request.status===409)msg='This visibility option is not configured. Choose Private to save your video safely.';
        if(request.status===419){
          uncertainFailure('Your session expired.');
        }else if([400,409,413,415,422].includes(request.status) && data.ok!==true){
          retryableFailure(msg);
        }else{
          uncertainFailure('The upload response was not confirmed.');
        }
      }
      finish();
    };
    request.onerror=()=>{
      if(xhr!==request)return;
      uncertainFailure('Your connection was interrupted.');
      finish();
    };
    request.ontimeout=()=>{
      if(xhr!==request)return;
      uncertainFailure('The upload timed out.');
      finish();
    };
    request.onabort=()=>{
      if(xhr!==request)return;
      uncertainFailure('The transfer was stopped in this browser.');
      finish();
    };
    try{request.send(payload);}
    catch(_){
      if(xhr===request){
        uncertainFailure('The browser could not send this upload.');
        finish();
      }
    }
  });
  window.addEventListener('beforeunload',event=>{
    if(!xhr)return;
    event.preventDefault();
    event.returnValue='';
  });
  if(ingest){form.hidden=true;setResult('Checking your saved upload...');startPoll();}
  window.addEventListener('pagehide',()=>{if(timer)clearInterval(timer);});
})();
</script>
</body>
</html>
