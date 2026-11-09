@extends('layouts.admin')
@section('title', 'Manage private upload')
@section('content')
<div class="mx-auto max-w-4xl">
  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <a href="{{ route('upload-studio.index') }}" class="text-xs font-bold text-rose-300 hover:underline">← Upload Studio</a>
      <h2 class="mt-3 text-2xl font-extrabold tracking-tight">Manage video</h2>
      <p class="mt-2 text-sm text-zinc-400">The video is saved privately. Run checks here only when preparing it for processing or distribution.</p>
    </div>
    <span class="rounded-full border border-white/20 px-4 py-2 text-xs font-bold text-zinc-200">{{ ucfirst($upload->status) }}</span>
  </div>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/60 p-5 md:p-7">
    <div class="grid gap-5 sm:grid-cols-2">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Uploaded file</p>
        <p class="mt-2 break-words text-sm font-semibold">{{ $upload->original_name }}</p>
      </div>
      <div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">File details</p>
        <p class="mt-2 text-sm">{{ number_format($upload->size_bytes / 1048576, 1) }} MB · {{ strtoupper($upload->ingestable_type) }}</p>
      </div>
      <div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Security scan</p>
        <p class="mt-2 text-sm text-zinc-300">{{ ucfirst($upload->security_scan_status ?? 'pending') }}</p>
      </div>
      <div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Cloud backup</p>
        <p class="mt-2 text-sm text-zinc-300">{{ match($upload->cloud_status) { 'ready' => 'Private R2 backup verified', 'pending' => 'Waiting for the media queue', 'failed' => 'Cloud backup needs attention', default => 'Local private storage' } }}</p>
      </div>
    </div>
    <div class="mt-6 rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 text-xs leading-6 text-amber-200">
      Authorizing a video here does not start streaming or make it public. You are responsible for confirming permission to distribute the actual video, not only the title's metadata.
    </div>
    @if($upload->status === 'quarantined')
    <div class="mt-7 grid gap-6 lg:grid-cols-2">
      <form method="POST" action="{{ route('admin.upload-studio.approve', $upload->id) }}" class="space-y-4 rounded-xl border border-white/15 bg-black/25 p-5">
        @csrf
        <h3 class="text-base font-bold">Authorize private processing</h3>
        <p class="text-xs leading-6 text-zinc-400">A trusted ClamAV scan runs automatically when you approve. The video stays private afterward.</p>
        <label class="block">
          <span class="mb-2 block text-xs font-semibold text-zinc-300">License or permission reference</span>
          <input name="rights_reference" type="text" required minlength="8" maxlength="180"
            value="{{ old('rights_reference') }}" placeholder="Your document or license reference"
            class="w-full rounded-xl border border-white/20 bg-zinc-950 px-4 py-3 text-sm text-white" autocomplete="off">
        </label>
        <label class="flex items-start gap-3 text-xs leading-6 text-zinc-300">
          <input name="rights_confirmed" value="1" required type="checkbox" class="mt-1 accent-rose-500">
          <span>I confirm that I have reviewed genuine permission to distribute this particular video worldwide. This is my operator attestation, not automatic legal verification.</span>
        </label>
        @unless($scannerReady)
          <p class="rounded-xl border border-red-600/30 bg-red-900/20 p-3 text-xs text-red-200">
            Approval is unavailable because ClamAV has not been configured. Uploads remain private; install and configure the scanner first.
          </p>
        @endunless
        <button type="submit" @disabled(!$scannerReady)
          class="w-full rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-40">
          Check and authorize
        </button>
      </form>
      <form method="POST" action="{{ route('admin.upload-studio.reject', $upload->id) }}" class="space-y-4 rounded-xl border border-white/15 bg-black/25 p-5">
        @csrf
        <h3 class="text-base font-bold">Reject this upload</h3>
        <p class="text-xs leading-6 text-zinc-400">Reject a wrong, unsafe or unauthorized video. The private file remains quarantined for operator cleanup.</p>
        <label class="block">
          <span class="mb-2 block text-xs font-semibold text-zinc-300">Reason</span>
          <textarea name="reason" required minlength="8" maxlength="1000" rows="4"
            class="w-full rounded-xl border border-white/20 bg-zinc-950 px-4 py-3 text-sm text-white" placeholder="Explain why this video cannot be approved">{{ old('reason') }}</textarea>
        </label>
        <button type="submit" class="w-full rounded-xl border border-white/25 px-5 py-3 text-sm font-bold text-zinc-200 hover:bg-white/5">Reject upload</button>
      </form>
    </div>
    @else
      <div class="mt-6 rounded-xl border border-white/15 bg-black/20 p-5 text-sm text-zinc-300">
        <strong class="mb-2 block text-zinc-100">Review completed</strong>
        <p>Current status: {{ ucfirst($upload->status) }}.</p>
        @if($upload->review_notes)<p class="mt-2 whitespace-pre-wrap break-words text-xs">{{ $upload->review_notes }}</p>@endif
        @if($upload->status === 'approved')<p class="mt-3 text-xs text-zinc-400">The media worker can process an approved ingest only when encoding is independently enabled. No public playback is activated here.</p>@endif
      </div>
    @endif
  </section>
</div>
@endsection
