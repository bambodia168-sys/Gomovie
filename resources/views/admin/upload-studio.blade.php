@extends('layouts.admin')
@section('title', 'Upload Studio')
@section('content')
<style>
.gm-upload-hero{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px}
.gm-upload-title{font-size:clamp(23px,3vw,31px);font-weight:900;letter-spacing:-.04em}
.gm-upload-muted{font-size:13px;color:#a5a5b3;line-height:1.7}
.gm-upload-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(235px,310px);gap:18px;align-items:start}
.gm-upload-box{padding:clamp(18px,3vw,30px);border-radius:19px;border:1px solid #ffffff1c;background:linear-gradient(145deg,#191923,#101014);box-shadow:0 16px 45px #0003}
.gm-upload-field{display:block;margin:17px 0}
.gm-upload-label{display:block;font-size:12px;font-weight:800;color:#e9e8f0;margin-bottom:8px}
.gm-upload-input{width:100%;min-height:46px;border:1px solid #ffffff29;border-radius:11px;background:#0c0d13;padding:11px 13px;color:#f5f5fa;font-size:13px}
.gm-upload-input:focus-visible{outline:2px solid #ef4b66;outline-offset:2px}
.gm-upload-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:9px;min-height:185px;padding:22px;border:2px dashed #ffffff35;border-radius:17px;background:radial-gradient(ellipse at center,#311b2b,#13131b 70%);cursor:pointer;transition:border-color .2s,background .2s}
.gm-upload-drop:hover,.gm-upload-drop.is-dragging{border-color:#f04b64;background:#291825}
.gm-upload-drop strong{font-weight:900;font-size:16px}
.gm-upload-drop small{color:#bdb1c2;font-size:12px}
.gm-upload-drop input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.gm-upload-drop span{display:grid;place-items:center;width:45px;height:45px;background:#ee29472b;border-radius:13px;font-size:24px;color:#ff7085}
.gm-upload-btn{display:inline-flex;align-items:center;justify-content:center;min-height:45px;border-radius:11px;border:0;background:#e72e4f;padding:10px 20px;font-weight:850;font-size:13px;color:#fff;cursor:pointer}
.gm-upload-btn:hover{background:#fa4363}
.gm-upload-btn:disabled{opacity:.5;cursor:not-allowed}
.gm-upload-progress{height:8px;border-radius:20px;background:#ffffff1e;overflow:hidden}
.gm-upload-progress>span{display:block;height:100%;width:0;background:linear-gradient(90deg,#fd9d54,#ec3155);transition:width .15s}
.gm-upload-info{border:1px solid #ffffff1b;background:#131219;border-radius:14px;padding:17px;margin-bottom:12px}
.gm-upload-info b{display:block;font-size:14px;margin-bottom:7px}
.gm-upload-info p{font-size:12px;color:#aaa3b2;line-height:1.75}
.gm-upload-status{padding:11px 13px;background:#1c1c27;border:1px solid #ffffff21;border-radius:10px;color:#ece2ee;font-size:12px}
.gm-upload-status:empty{display:none}
.gm-upload-status[hidden],.gm-upload-progress-wrap[hidden]{display:none}
.gm-upload-history{margin-top:23px;overflow:hidden;border:1px solid #ffffff1b;border-radius:16px;background:#15141b}
.gm-upload-history h2{font-size:15px;font-weight:850}
.gm-upload-history table{width:100%;min-width:640px;border-collapse:collapse;text-align:left;font-size:12px}
.gm-upload-history th{font-size:10px;letter-spacing:.05em;text-transform:uppercase;color:#aaa0b1;padding:15px;border-bottom:1px solid #ffffff20}
.gm-upload-history td{padding:15px;border-bottom:1px solid #ffffff13;color:#d2ccda}
.gm-upload-history tr:last-child td{border-bottom:0}
.gm-upload-link{color:#ff8297;font-weight:850;text-decoration:none}
.gm-upload-pill{display:inline-block;padding:5px 8px;border-radius:20px;border:1px solid #ffffff30;background:#ffffff0a;color:#ddd1e2;font-size:10px;font-weight:750}
@media(max-width:850px){.gm-upload-grid{grid-template-columns:1fr}}
</style>
<div class="gm-upload-hero">
  <div>
    <h2 class="gm-upload-title">Upload your video</h2>
    <p class="gm-upload-muted">Select a movie or episode, choose your file, then upload. We'll keep it private while it's reviewed.</p>
  </div>
  <a href="{{ route('admin.media.index') }}" class="rounded-xl border border-white/20 px-4 py-3 text-xs font-bold text-zinc-200 hover:bg-white/5">Media Library</a>
</div>
<div class="gm-upload-grid">
  <section class="gm-upload-box" aria-labelledby="gm-upload-form-heading">
    <h3 id="gm-upload-form-heading" class="text-lg font-bold">New video</h3>
    <p class="gm-upload-muted">MP4, MOV, MKV or WebM · Up to 128 MB per upload</p>
    <form id="gm-upload-studio-form" action="{{ route('admin.upload-studio.upload') }}" method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      <label class="gm-upload-field">
        <span class="gm-upload-label">Content type</span>
        <select id="gm-upload-type" class="gm-upload-input" name="type" required>
          <option value="movie">Movie</option>
          <option value="episode">TV episode</option>
        </select>
      </label>
      <label class="gm-upload-field">
        <span class="gm-upload-label">Find your title</span>
        <input id="gm-upload-search" class="gm-upload-input" type="search" placeholder="Search the list" autocomplete="off">
      </label>
      <label class="gm-upload-field">
        <span class="gm-upload-label">Movie or episode</span>
        <select id="gm-upload-target" class="gm-upload-input" name="title_id" required>
          <option value="">Choose a title</option>
          @foreach($movies as $movie)
            <option data-kind="movie" value="{{ $movie->id }}">{{ $movie->title }}</option>
          @endforeach
          @foreach($episodes as $episode)
            <option data-kind="episode" value="{{ $episode->id }}">{{ $episode->season?->series?->title ?? 'Series' }} · Season {{ $episode->season?->season_number ?? '?' }} · Episode {{ $episode->episode_number }} — {{ $episode->title }}</option>
          @endforeach
        </select>
      </label>
      <div class="gm-upload-field">
        <span class="gm-upload-label">Video file</span>
        <label class="gm-upload-drop" id="gm-upload-drop">
          <span aria-hidden="true">↑</span>
          <strong id="gm-upload-file-label">Drag a video here or browse</strong>
          <small>Private upload. Your video is not published automatically.</small>
          <input id="gm-upload-file" name="video" type="file" accept=".mp4,.mov,.mkv,.webm,video/mp4,video/quicktime,video/x-matroska,video/webm" required aria-label="Choose video file">
        </label>
      </div>
      <div class="gm-upload-progress-wrap mb-4" id="gm-upload-progress-wrap" hidden>
        <div class="mb-2 flex justify-between text-xs text-zinc-300"><span>Uploading</span><b id="gm-upload-percent">0%</b></div>
        <div class="gm-upload-progress" role="progressbar" aria-label="Video upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="gm-upload-progress"><span id="gm-upload-progress-fill"></span></div>
      </div>
      <p class="gm-upload-status mb-4" id="gm-upload-feedback" role="status" aria-live="polite" hidden></p>
      <div class="flex flex-wrap items-center gap-3">
        <button class="gm-upload-btn" id="gm-upload-submit" type="submit">Upload video →</button>
        <button type="button" class="rounded-lg border border-white/25 px-4 py-3 text-xs text-zinc-300 hover:bg-white/5" id="gm-upload-cancel" hidden>Cancel</button>
        <a href="#" class="gm-upload-link text-xs" id="gm-upload-review-link" hidden>Open upload review →</a>
      </div>
    </form>
  </section>
  <aside aria-label="Upload information">
    <div class="gm-upload-info">
      <b>① Upload</b><p>Select a video once. The file is kept private while the review takes place.</p>
    </div>
    <div class="gm-upload-info">
      <b>② Review</b><p>An administrator checks rights evidence and the automated security scan when available.</p>
    </div>
    <div class="gm-upload-info">
      <b>③ Prepare</b><p>Approved videos can be processed into HLS. Public playback stays off until separate distribution checks pass.</p>
    </div>
    <div class="gm-upload-info">
      <b>Storage</b>
      <p>Local private storage <span class="gm-upload-pill">Ready</span></p>
      <p class="mt-2">Cloudflare R2 <span class="gm-upload-pill">{{ $r2Configured ? 'Private mirror enabled' : 'Optional · not configured' }}</span></p>
      <p class="mt-2">Cloud credentials are configured by the site owner on the server; uploaders never need them.</p>
    </div>
    @unless($scannerReady)
      <div class="gm-upload-info"><b>Review scanner not configured</b><p>You can upload videos now. Approval remains locked until a trusted malware scanner is installed by the administrator.</p></div>
    @endunless
  </aside>
</div>
<section class="gm-upload-history" aria-labelledby="gm-upload-history-heading">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-5">
    <h2 id="gm-upload-history-heading">Recent uploads</h2>
    <span class="gm-upload-muted">Private media intake</span>
  </div>
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Video</th><th>Uploader</th><th>Size</th><th>Review</th><th>Cloud backup</th><th>Action</th></tr></thead>
      <tbody>
      @forelse($uploads as $upload)
        <tr>
          <td><strong class="block max-w-[230px] truncate text-zinc-100" title="{{ $upload->original_name }}">{{ $upload->original_name }}</strong><small class="text-zinc-500">{{ ucfirst($upload->ingestable_type) }}</small></td>
          <td>{{ $upload->uploader }}</td>
          <td>{{ number_format($upload->size_bytes / 1048576, 1) }} MB</td>
          <td><span class="gm-upload-pill">{{ ucfirst($upload->status) }}</span></td>
          <td><span class="gm-upload-pill">{{ match($upload->cloud_status) { 'ready' => 'Saved to R2', 'pending' => 'Pending', 'failed' => 'Needs retry', default => 'Local only' } }}</span></td>
          <td><a class="gm-upload-link" href="{{ route('admin.upload-studio.review', $upload->id) }}">View →</a></td>
        </tr>
      @empty
        <tr><td colspan="6" class="py-8 text-center text-zinc-500">Your videos will appear here after upload.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
<div class="mt-5">{{ $uploads->links() }}</div>
<script>
(() => {
  const form = document.getElementById('gm-upload-studio-form');
  if (!form) return;
  const type = document.getElementById('gm-upload-type');
  const target = document.getElementById('gm-upload-target');
  const search = document.getElementById('gm-upload-search');
  const input = document.getElementById('gm-upload-file');
  const drop = document.getElementById('gm-upload-drop');
  const fileLabel = document.getElementById('gm-upload-file-label');
  const submit = document.getElementById('gm-upload-submit');
  const cancel = document.getElementById('gm-upload-cancel');
  const feedback = document.getElementById('gm-upload-feedback');
  const progressWrap = document.getElementById('gm-upload-progress-wrap');
  const progress = document.getElementById('gm-upload-progress');
  const progressFill = document.getElementById('gm-upload-progress-fill');
  const percent = document.getElementById('gm-upload-percent');
  const reviewLink = document.getElementById('gm-upload-review-link');
  let xhr = null;
  function filter() {
    const q = search.value.trim().toLowerCase();
    for (const option of target.options) {
      if (!option.value) continue;
      option.hidden = option.dataset.kind !== type.value ||
        (q && !option.textContent.toLowerCase().includes(q));
    }
    if (target.selectedOptions[0]?.hidden) target.value = '';
  }
  function show(message) {
    feedback.textContent = message;
    feedback.hidden = false;
  }
  function setBusy(busy) {
    submit.disabled = busy;
    input.disabled = busy;
    target.disabled = busy;
    type.disabled = busy;
    cancel.hidden = !busy;
  }
  type.addEventListener('change', () => { target.value = ''; filter(); });
  search.addEventListener('input', filter);
  filter();
  for (const event of ['dragenter', 'dragover']) drop.addEventListener(event, () => drop.classList.add('is-dragging'));
  for (const event of ['dragleave', 'drop']) drop.addEventListener(event, () => drop.classList.remove('is-dragging'));
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    fileLabel.textContent = file ? file.name : 'Drag a video here or browse';
  });
  cancel.addEventListener('click', () => xhr?.abort());
  form.addEventListener('submit', event => {
    event.preventDefault();
    const file = input.files?.[0];
    if (!target.value) return show('Choose the movie or episode first.');
    if (!file) return show('Choose a video file to upload.');
    if (file.size > 128 * 1024 * 1024) return show('The file exceeds 128 MB. Choose a smaller video.');
    if (file.size === 0) return show('The selected video is empty.');
    const token = form.querySelector('input[name="_token"]')?.value;
    if (!token) return show('Refresh this page and try again.');

    reviewLink.hidden = true;
    feedback.hidden = true;
    progressWrap.hidden = false;
    progressFill.style.width = '0%';
    progress.setAttribute('aria-valuenow', '0');
    percent.textContent = '0%';
    xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', token);
    xhr.upload.addEventListener('progress', e => {
      if (!e.lengthComputable) return;
      const value = Math.min(100, Math.round(e.loaded / e.total * 100));
      progressFill.style.width = value + '%';
      percent.textContent = value + '%';
      progress.setAttribute('aria-valuenow', String(value));
    });
    xhr.onload = () => {
      let data = {};
      try { data = JSON.parse(xhr.responseText); } catch { /* No sensitive data shown. */ }
      if (xhr.status === 201 && data.ok) {
        show(data.message || 'Video uploaded privately.');
        if (typeof data.review_url === 'string') {
          const next = new URL(data.review_url, location.origin);
          if (next.origin === location.origin) {
            reviewLink.href = next.href;
            reviewLink.hidden = false;
          }
        }
        submit.textContent = 'Upload another video';
      } else {
        const error = data?.errors ? Object.values(data.errors).flat()[0] : data?.message;
        show(error || 'Upload could not be completed. Please retry.');
      }
      setBusy(false);
      xhr = null;
    };
    xhr.onerror = () => { show('Network interrupted. Please retry.'); setBusy(false); xhr = null; };
    xhr.onabort = () => { show('Upload canceled.'); setBusy(false); xhr = null; };
    setBusy(true);
    show('Uploading privately. Keep this tab open until complete.');
    xhr.send(new FormData(form));
  });
})();
</script>
@endsection
