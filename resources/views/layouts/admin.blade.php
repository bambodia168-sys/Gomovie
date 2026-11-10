<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b0c11">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Studio') — Go Movie Studio</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('partials.feedback')
@include('partials.brand-head')
@include('partials.header-ui')
@include('partials.workspace-ui')
</head>
<body class="gm-studio-shell gm-workspace-shell antialiased" data-gm-workspace="studio">
<a href="#studio-content" class="gm-skip">Skip to content</a>
@php $workspaceMode='admin'; @endphp
<div class="gm-workspace-frame">
  @include('partials.workspace-sidebar')
  <div class="gm-studio-main gm-workspace-main">
    @include('partials.workspace-header')
    <main id="studio-content" class="gm-workspace-content">
      @yield('content')
    </main>
  </div>
</div>
<button class="gm-workspace-backdrop" type="button" data-gm-sidebar-backdrop
        aria-label="Close navigation" hidden></button>
</body>
</html>
