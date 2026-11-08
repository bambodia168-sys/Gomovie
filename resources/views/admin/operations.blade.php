@extends('layouts.admin')
@section('title','System Health')
@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
  <div><p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">Operations / Read-only</p>
    <h2 class="mt-2 text-3xl font-black tracking-tight">Application health</h2>
    <p class="mt-3 text-sm text-zinc-400">Database, queues, storage and integration safety checks. This view does not approve production release.</p>
  </div>
  <p class="rounded-full border border-white/15 bg-zinc-900 px-4 py-2 text-xs text-zinc-400">Checked {{ $snapshot['checked_at'] }}</p>
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
<section class="mt-9 overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/65">
  <h3 class="border-b border-white/10 px-6 py-5 text-lg font-semibold">Recent administrative audit events</h3>
  <div class="overflow-x-auto"><table class="w-full min-w-[530px] text-left text-sm">
    <thead class="text-xs uppercase text-zinc-500"><tr><th class="p-5">Action</th><th class="p-5">Resource type</th><th class="p-5">Recorded</th></tr></thead>
    <tbody class="divide-y divide-white/10">
      @forelse($recentEvents as $event)
        <tr><td class="p-5">{{ $event->action }}</td><td class="p-5 text-zinc-400">{{ $event->resource_type ?: '—' }}</td><td class="p-5 text-zinc-400">{{ $event->created_at }}</td></tr>
      @empty
        <tr><td colspan="3" class="p-7 text-center text-zinc-500">No administrative events recorded.</td></tr>
      @endforelse
    </tbody>
  </table></div>
</section>
@endsection
