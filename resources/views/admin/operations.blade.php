@extends('layouts.admin')
@section('title','System Health')
@section('content')
<div class="gm-page-heading">
  <div><p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">Operations / Read-only</p>
    <h2>Application health</h2>
    <p class="mt-3 text-sm text-zinc-400">Database, queues, storage and integration safety checks. This view does not approve production release.</p>
  </div>
  <div class="gm-page-actions"><span class="gm-studio-pill">Checked {{ $snapshot['checked_at'] }}</span><a href="{{ route('admin.operations.index') }}" class="gm-studio-button">@include('partials.studio-icon',['name'=>'history']) Refresh checks</a></div>
</div>
@if(count($snapshot['alerts']))
  <section class="mb-7 rounded-2xl border border-amber-600/25 bg-zinc-900/70 px-6 py-5" role="status">
    <h3 class="font-semibold text-[#F5C518]">Action required</h3>
    <ul class="mt-3 list-inside list-disc space-y-2 text-sm text-zinc-300">
      @foreach($snapshot['alerts'] as $alert)<li>{{ $alert }}</li>@endforeach
    </ul>
  </section>
@else
  <p class="mb-7 text-sm text-zinc-400">No application alarms were detected. External acceptance gates remain separate.</p>
@endif
<section><h3 class="mb-4 text-lg font-semibold">Service checks</h3>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  @foreach($snapshot['checks'] as $name=>$status)
    <div class="rounded-2xl border border-white/10 bg-zinc-900/65 p-5">
      <p class="text-sm capitalize text-zinc-400">{{ str_replace('_',' ',$name) }}</p>
      <p class="mt-3 text-lg font-semibold capitalize {{ $status==='healthy' ? 'text-emerald-300' : ($status==='unavailable' || $status==='incomplete' ? 'text-red-300' : 'text-[#F5C518]') }}">{{ str_replace('_',' ',$status) }}</p>
    </div>
  @endforeach
</div></section>
<section class="mt-9"><h3 class="mb-4 text-lg font-semibold">Operational metrics</h3>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  @foreach($snapshot['metrics'] as $name=>$value)
    <div class="rounded-2xl border border-white/10 bg-zinc-900/65 p-5">
      <p class="text-xs capitalize tracking-wide text-zinc-400">{{ str_replace('_',' ',$name) }}</p>
      <p class="mt-3 text-3xl font-bold tabular-nums">{{ $value === null ? '—' : number_format($value) }}</p>
    </div>
  @endforeach
</div></section>
<div class="mt-9 grid gap-6 xl:grid-cols-2">
  <section class="rounded-2xl border border-white/10 bg-zinc-900/65 p-6">
    <h3 class="text-lg font-semibold">Queue worker execution</h3>
    <p class="mt-2 text-xs leading-5 text-zinc-400">Processed probes confirm a worker handled queued work, not uninterrupted availability.</p>
    <dl class="mt-6 space-y-4">
    @foreach($snapshot['queue_probes'] as $name=>$probe)
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
        <dt class="font-medium">{{ $name }}</dt>
        <dd class="text-sm text-zinc-400">{{ $probe ? $probe['seconds_ago'].' seconds since completion' : 'No completion recorded' }}</dd>
      </div>
    @endforeach
    </dl>
  </section>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/65 p-6">
    <h3 class="text-lg font-semibold">Integration safety switches</h3>
    <p class="mt-2 text-xs text-zinc-400">Read-only status. Enable only after independent acceptance.</p>
    <dl class="mt-6 space-y-4">
    @foreach($snapshot['feature_flags'] as $name=>$enabled)
      <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-4">
        <dt class="text-sm capitalize">{{ str_replace('_',' ',$name) }}</dt>
        <dd class="text-xs {{ $enabled ? 'text-[#F5C518]' : 'text-zinc-400' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</dd>
      </div>
    @endforeach
    </dl>
  </section>
</div>
<section class="gm-studio-panel mt-9">
  <div class="gm-studio-panel-head"><div><h3>Recent administrative audit events</h3><small>Read-only security history · 10 records per page</small></div><span class="gm-studio-pill">{{ $recentEvents->total() }} events</span></div>
  <div class="gm-studio-table-scroll"><table class="gm-studio-table">
    <thead><tr><th>No.</th><th>Action</th><th>Resource type</th><th>Resource ID</th><th>Recorded</th></tr></thead>
    <tbody>
      @forelse($recentEvents as $event)
        <tr><td>{{ ($recentEvents->firstItem()??1)+$loop->index }}</td><td>{{ $event->action }}</td><td class="gm-studio-muted">{{ $event->resource_type ?: '—' }}</td><td class="gm-studio-muted">{{ $event->resource_id ?: '—' }}</td><td class="gm-studio-muted">{{ $event->created_at }}</td></tr>
      @empty
        <tr><td colspan="5" class="gm-studio-empty">No administrative events recorded.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  @include('partials.studio-pager',['paginator'=>$recentEvents,'id'=>'gm-audit-page'])
</section>
<section class="mt-9 overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/65">
  <div class="border-b border-white/10 px-6 py-5">
    <p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">Independent acceptance</p>
    <h3 class="mt-2 text-lg font-semibold">aaPanel &amp; cPanel release-evidence register</h3>
    <p class="mt-2 max-w-3xl text-xs leading-5 text-zinc-400">Read-only evidence recorded by server operators. A separate verified super-admin must inspect actual host tests. Accepted references do not automatically enable production release, media playback or payments.</p>
  </div>
  <div class="grid gap-6 p-6 xl:grid-cols-2">
  @foreach($hostAcceptance['targets'] as $target)
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-zinc-950/70">
      <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
        <h4 class="font-semibold">{{ $target['label'] }}</h4>
        <span class="text-xs text-[#F5C518]">{{ $target['accepted'] }} / {{ $target['total'] }} reviewed</span>
      </div>
      <div class="max-h-[520px] overflow-x-auto overflow-y-auto">
        <table class="w-full min-w-[380px] text-left text-xs">
          <thead class="sticky top-0 bg-zinc-950 text-zinc-500"><tr><th class="px-5 py-3">Acceptance check</th><th class="px-5 py-3">Evidence</th></tr></thead>
          <tbody class="divide-y divide-white/5">
            @foreach($target['checks'] as $check)
              <tr>
                <td class="px-5 py-3 align-top">
                  <span class="block text-zinc-200">{{ $check['name'] }}</span>
                  <span class="mt-1 block text-[10px] text-zinc-500">{{ $check['id'] }}</span>
                </td>
                <td class="px-5 py-3 align-top">
                  <span class="{{ $check['status']==='accepted' ? 'text-emerald-300' : ($check['status']==='rejected' ? 'text-red-300' : 'text-[#F5C518]') }}">{{ ucfirst(str_replace('_',' ',$check['status'])) }}</span>
                  @if($check['evidence_reference'])<span class="mt-1 block max-w-[170px] break-words text-[10px] text-zinc-500">{{ $check['evidence_reference'] }}</span>@endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach
  </div>
  <p class="border-t border-white/10 px-6 py-4 text-xs text-zinc-500">Production release accepted: No. Real host, restore, email, device, rights and payment checks remain external gates.</p>
</section>
@endsection
