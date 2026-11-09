@extends('layouts.account')
@section('title','My Account')
@section('account_content')
<div class="gm-page-heading">
  <div><p class="text-xs uppercase tracking-widest text-rose-300">Your workspace</p>
    <h1>Welcome, {{ auth()->user()->name }}</h1><p>Manage your private library, viewing profiles, and account security.</p>
  </div>
  <div class="gm-page-actions">
    <a class="gm-studio-button" href="{{ route('account.library',['section'=>'watchlist']) }}">@include('partials.studio-icon',['name'=>'heart']) My List</a>
    <a class="gm-studio-button is-primary" href="{{ route('account.settings') }}">@include('partials.studio-icon',['name'=>'settings']) Account settings</a>
  </div>
</div>
@php
  $profileCount=\App\Models\UserProfile::where('user_id',auth()->id())->count();
  $watchlistCount=\Illuminate\Support\Facades\DB::table('watchlists')->whereIn('user_profile_id',
      \App\Models\UserProfile::where('user_id',auth()->id())->select('id'))->count();
  $historyCount=\Illuminate\Support\Facades\DB::table('watch_history')->where('user_id',auth()->id())->count();
@endphp
<div class="gm-studio-metrics">
  <a class="gm-studio-metric" href="{{ route('account.library',['section'=>'watchlist']) }}"><small>Saved titles</small><strong>{{ $watchlistCount }}</strong></a>
  <a class="gm-studio-metric" href="{{ route('account.library',['section'=>'history']) }}"><small>Watch history</small><strong>{{ $historyCount }}</strong></a>
  <a class="gm-studio-metric" href="{{ route('account.settings') }}"><small>Viewer profiles</small><strong>{{ $profileCount }}</strong></a>
  <div class="gm-studio-metric"><small>Email verification</small><strong class="text-lg {{ auth()->user()->hasVerifiedEmail()?'text-emerald-300':'text-amber-300' }}">{{ auth()->user()->hasVerifiedEmail()?'Verified':'Pending' }}</strong></div>
</div>
<div class="grid gap-5 mt-6 lg:grid-cols-2">
  <section class="gm-studio-panel"><div class="gm-studio-panel-head"><div><h3>Account details</h3><small>Sign-in information</small></div><a class="gm-studio-button" href="{{ route('account.settings') }}">Edit</a></div>
    <div class="gm-studio-panel-body"><p class="text-sm text-zinc-400">Name</p><p class="font-bold mt-1 mb-4">{{ auth()->user()->name }}</p><p class="text-sm text-zinc-400">Email</p><p class="font-semibold mt-1 break-all">{{ auth()->user()->email }}</p></div></section>
  <section class="gm-studio-panel"><div class="gm-studio-panel-head"><div><h3>Subscription</h3><small>Current service availability</small></div></div>
    <div class="gm-studio-panel-body"><span class="gm-studio-pill is-warn">Purchases unavailable</span><p class="mt-4 text-sm text-zinc-400">Subscription purchases will become available only after provider onboarding, rights compliance and billing acceptance.</p></div></section>
</div>
@endsection
