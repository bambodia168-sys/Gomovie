<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0b0c11">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Studio') — Gomovie Studio</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('partials.feedback')
@include('partials.brand-head')
@include('partials.header-ui')
</head>
<body class="gm-studio-shell antialiased" x-data="{menu:false}">
<div class="min-h-screen flex">
  <aside class="gm-studio-sidebar" :class="menu ? 'is-open' : ''" @keydown.escape="menu=false" aria-label="Management Studio navigation">
    <a class="gm-studio-brand" href="{{ route('admin.dashboard') }}" aria-label="Go Movie Management Studio">@include('partials.brand-logo')<small>MANAGEMENT STUDIO</small></a>
    <nav aria-label="Management Studio">
      <p class="gm-studio-nav-heading">Overview</p>
      <a href="{{ route('admin.dashboard') }}" @class(['is-current'=>request()->routeIs('admin.dashboard')])>@include('partials.studio-icon',['name'=>'grid']) Dashboard</a>
      <p class="gm-studio-nav-heading">Content management</p>
      <a href="{{ route('admin.catalog.index','movies') }}" @class(['is-current'=>request()->routeIs('admin.catalog.*')&&request()->route('type')==='movies'])>@include('partials.studio-icon',['name'=>'film']) Movies</a>
      <a href="{{ route('admin.catalog.index','series') }}" @class(['is-current'=>request()->routeIs('admin.catalog.*')&&request()->route('type')==='series'])>@include('partials.studio-icon',['name'=>'series']) TV Series</a>
      @if(in_array(auth()->user()->role,['admin','super_admin'],true))
      <a href="{{ route('upload-studio.index') }}" @class(['is-current'=>request()->routeIs('upload-studio.*')])>@include('partials.studio-icon',['name'=>'upload']) Upload Studio</a>
      @endif
      <a href="{{ route('admin.media.index') }}" @class(['is-current'=>request()->routeIs('admin.media.*')])>@include('partials.studio-icon',['name'=>'media']) Media Library</a>
      @if(in_array(auth()->user()->role,['admin','super_admin'],true))
      <p class="gm-studio-nav-heading">Appearance & configuration</p>
      <a href="{{ route('admin.appearance.index') }}" @class(['is-current'=>request()->routeIs('admin.appearance.*')])>@include('partials.studio-icon',['name'=>'appearance']) Appearance</a>
      <a href="{{ route('admin.player-settings.edit') }}" @class(['is-current'=>request()->routeIs('admin.player-settings.*')])>@include('partials.studio-icon',['name'=>'settings']) Player Settings</a>
      <p class="gm-studio-nav-heading">Administration</p>
      <a href="{{ route('admin.users.index') }}" @class(['is-current'=>request()->routeIs('admin.users.*')])>@include('partials.studio-icon',['name'=>'users']) User Manager</a>
      <a href="{{ route('admin.operations.index') }}" @class(['is-current'=>request()->routeIs('admin.operations.*')])>@include('partials.studio-icon',['name'=>'shield']) System Health</a>
      @endif
      <p class="gm-studio-nav-heading">Workspace</p>
      <a href="{{ route('account.overview') }}">@include('partials.studio-icon',['name'=>'home']) My Account</a>
      <a href="{{ route('browse') }}">@include('partials.studio-icon',['name'=>'external']) View website</a>
    </nav>
    <div class="gm-studio-identity">Signed in as <strong class="text-white">{{ auth()->user()->name }}</strong></div>
  </aside>
  <div class="gm-studio-main">
    <header class="gm-studio-topbar">
      <div class="flex items-center gap-3">
        <button type="button" class="gm-studio-button lg:hidden" @click="menu=!menu" :aria-expanded="menu.toString()" aria-label="Toggle menu">☰</button>
        <div><small>Gomovie Studio</small><h1>@yield('title','Dashboard')</h1></div>
      </div>
      <div class="gm-studio-top-actions">
        @include('partials.header-actions')
      </div>
    </header>
    <main id="studio-content">@yield('content')</main>
  </div>
</div>
</body></html>
