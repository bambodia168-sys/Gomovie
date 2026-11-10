{{-- Compact shared header for Studio and personal account workspaces.
     No breadcrumbs. Search always goes to the existing public catalog search. --}}
<header class="gm-studio-topbar gm-workspace-topbar" role="banner">
  <div class="gm-workspace-header-start">
    <button type="button" class="gm-workspace-toggle" data-gm-sidebar-toggle
            aria-label="Collapse sidebar" aria-expanded="true"
            aria-controls="gm-workspace-sidebar" title="Collapse sidebar">
      <svg class="gm-nav-icon" viewBox="0 0 24 24" width="18" height="18"
           fill="none" stroke="currentColor" stroke-width="1.9"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 5h16M4 12h16M4 19h16"/>
      </svg>
    </button>
    <h1 class="gm-workspace-header-title">@yield('title', $workspaceMode === 'admin' ? 'Dashboard' : 'My Account')</h1>
  </div>

  <form class="gm-workspace-header-search" role="search"
        method="GET" action="{{ route('browse') }}">
    <label for="gm-workspace-search" class="sr-only">Search movies and series</label>
    <svg class="gm-nav-icon" viewBox="0 0 24 24" width="18" height="18"
         fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="10.7" cy="10.7" r="6.5"/><path d="m16 16 4.5 4.5"/>
    </svg>
    <input id="gm-workspace-search" type="search" name="q"
           maxlength="100" value="{{ request()->routeIs('browse') ? request('q') : '' }}"
           placeholder="Search movies &amp; series" autocomplete="off">
  </form>

  <div class="gm-workspace-header-end">
    <a href="{{ route('home') }}" class="gm-header-tool gm-workspace-home"
       aria-label="Home" title="Home">
      @include('partials.studio-icon',['name'=>'home'])
    </a>
    @include('partials.header-actions')
  </div>
</header>
