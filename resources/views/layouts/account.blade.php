@extends('layouts.app')
@section('content')
<div class="gm-account-shell">
  <aside class="gm-account-nav" aria-label="Account sections">
    <div class="gm-account-identity">
      <span class="gm-account-avatar">@include('partials.account-avatar')</span>
      <div><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_',' ',ucfirst(auth()->user()->role)) }}</small></div>
    </div>
    <p class="gm-studio-nav-heading">Workspace</p>
    <a href="{{ route('account.overview') }}" @class(['is-current'=>request()->routeIs('account.overview')])>@include('partials.studio-icon',['name'=>'grid']) Overview</a>
    <p class="gm-studio-nav-heading">Your library</p>
    <a href="{{ route('account.library',['section'=>'watchlist']) }}" @class(['is-current'=>request()->routeIs('account.library')&&request()->route('section')==='watchlist'])>@include('partials.studio-icon',['name'=>'heart']) My List</a>
    <a href="{{ route('account.library',['section'=>'history']) }}" @class(['is-current'=>request()->routeIs('account.library')&&request()->route('section')==='history'])>@include('partials.studio-icon',['name'=>'history']) Watch history</a>
    <p class="gm-studio-nav-heading">Account</p>
    <a href="{{ route('account.settings') }}" @class(['is-current'=>request()->routeIs('account.settings')])>@include('partials.studio-icon',['name'=>'settings']) Settings & profiles</a>
    @if(in_array(auth()->user()->role,['admin','super_admin','editor'],true))
    <a href="{{ route('admin.dashboard') }}">@include('partials.studio-icon',['name'=>'grid']) Management Studio</a>
    @endif
    <div class="gm-account-nav-bottom"><a href="{{ route('browse') }}">@include('partials.studio-icon',['name'=>'external']) Explore catalog</a></div>
  </aside>
  <div class="gm-account-main">
    @yield('account_content')
  </div>
</div>
@endsection