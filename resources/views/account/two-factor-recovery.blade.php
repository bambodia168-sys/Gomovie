@extends('layouts.account')
@section('title','Two-factor recovery codes')
@section('account_content')
<div class="gm-page-heading"><div><h1>Save your recovery codes</h1><p>Two-factor authentication is enabled.</p></div></div>
<section class="gm-studio-panel max-w-2xl" aria-labelledby="gm-recovery-heading">
  <div class="gm-studio-panel-head"><h3 id="gm-recovery-heading">One-time recovery codes</h3></div>
  <div class="gm-studio-panel-body">
    <p class="text-sm gm-studio-muted">Store these codes securely. Each code can sign you in once. This is the only time these codes are shown.</p>
    <ul class="my-5 grid gap-2 sm:grid-cols-2">
      @foreach($codes as $code)
        <li class="rounded-lg border px-3 py-2"><code class="break-all text-sm">{{ $code }}</code></li>
      @endforeach
    </ul>
    <a class="gm-studio-button is-primary" href="{{ route('account.two-factor.show') }}">Done</a>
  </div>
</section>
@endsection
