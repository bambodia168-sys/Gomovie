{{-- Shared navigation shell: consistent appearance, role-scoped functions. --}}
@php
  $workspaceMode = $workspaceMode ?? 'account';
  $workspaceIsStudio = $workspaceMode === 'admin';
  $workspaceIsAdmin = \App\Support\StudioNavigation::administrator(auth()->user());
  $workspaceIsStaff = \App\Support\StudioNavigation::staff(auth()->user());
@endphp
<aside id="gm-workspace-sidebar" class="gm-studio-sidebar gm-workspace-sidebar {{ $workspaceIsStudio ? '' : 'gm-account-nav' }}"
       data-gm-sidebar aria-label="{{ $workspaceIsStudio ? 'Management Studio navigation' : 'My Account navigation' }}">
  <a class="gm-studio-brand gm-workspace-brand" href="{{ $workspaceIsStudio ? route('admin.dashboard') : route('account.overview') }}"
     aria-label="{{ $workspaceIsStudio ? 'Management Studio' : 'My Account' }}">
    <span class="gm-workspace-brand-expanded">
      @include('partials.brand-logo')
      <small>{{ $workspaceIsStudio ? 'MANAGEMENT STUDIO' : 'MY ACCOUNT' }}</small>
    </span>
    <span class="gm-workspace-brand-compact" aria-hidden="true">
      <img src="{{ asset('favicon-32x32.png') }}" alt="" width="34" height="34">
    </span>
  </a>
  <nav class="gm-workspace-sidebar-nav" aria-label="{{ $workspaceIsStudio ? 'Studio pages' : 'Account pages' }}">
    @if($workspaceIsStudio)
      <p class="gm-studio-nav-heading">Overview</p>
      <a href="{{ route('admin.dashboard') }}" @class(['is-current'=>request()->routeIs('admin.dashboard')])
         aria-label="Dashboard" title="Dashboard" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
        @include('partials.studio-icon',['name'=>'grid'])<span class="gm-workspace-link-label">Dashboard</span>
      </a>

      <p class="gm-studio-nav-heading">Content management</p>
      <a href="{{ route('admin.catalog.index','movies') }}"
         @class(['is-current'=>request()->routeIs('admin.catalog.*') && request()->route('type')==='movies'])
         aria-label="Movies" title="Movies">
        @include('partials.studio-icon',['name'=>'film'])<span class="gm-workspace-link-label">Movies</span>
      </a>
      <a href="{{ route('admin.catalog.index','series') }}"
         @class(['is-current'=>request()->routeIs('admin.catalog.*') && request()->route('type')==='series'])
         aria-label="TV Series" title="TV Series">
        @include('partials.studio-icon',['name'=>'series'])<span class="gm-workspace-link-label">TV Series</span>
      </a>
      @if($workspaceIsAdmin)
        <a href="{{ route('upload-studio.index') }}" @class(['is-current'=>request()->routeIs('upload-studio.*')])
           aria-label="Upload Studio" title="Upload Studio">
          @include('partials.studio-icon',['name'=>'upload'])<span class="gm-workspace-link-label">Upload Studio</span>
        </a>
      @endif
      <a href="{{ route('admin.media.index') }}" @class(['is-current'=>request()->routeIs('admin.media.*')])
         aria-label="Media Library" title="Media Library">
        @include('partials.studio-icon',['name'=>'media'])<span class="gm-workspace-link-label">Media Library</span>
      </a>

      @if($workspaceIsAdmin)
        <p class="gm-studio-nav-heading">Appearance &amp; configuration</p>
        <a href="{{ route('admin.appearance.index') }}" @class(['is-current'=>request()->routeIs('admin.appearance.*')])
           aria-label="Appearance" title="Appearance">
          @include('partials.studio-icon',['name'=>'appearance'])<span class="gm-workspace-link-label">Appearance</span>
        </a>
        <a href="{{ route('admin.player-settings.edit') }}" @class(['is-current'=>request()->routeIs('admin.player-settings.*')])
           aria-label="Player Settings" title="Player Settings">
          @include('partials.studio-icon',['name'=>'settings'])<span class="gm-workspace-link-label">Player Settings</span>
        </a>
        <p class="gm-studio-nav-heading">Administration</p>
        <a href="{{ route('admin.users.index') }}" @class(['is-current'=>request()->routeIs('admin.users.*')])
           aria-label="User Manager" title="User Manager">
          @include('partials.studio-icon',['name'=>'users'])<span class="gm-workspace-link-label">User Manager</span>
        </a>
        <a href="{{ route('admin.operations.index') }}" @class(['is-current'=>request()->routeIs('admin.operations.*')])
           aria-label="System Health" title="System Health">
          @include('partials.studio-icon',['name'=>'shield'])<span class="gm-workspace-link-label">System Health</span>
        </a>
      @endif

      <p class="gm-studio-nav-heading">Workspace</p>
      <a href="{{ route('account.overview') }}" aria-label="My Account" title="My Account">
        @include('partials.studio-icon',['name'=>'home'])<span class="gm-workspace-link-label">My Account</span>
      </a>
      <a href="{{ route('browse') }}" aria-label="View website" title="View website">
        @include('partials.studio-icon',['name'=>'external'])<span class="gm-workspace-link-label">View website</span>
      </a>
    @else
      <p class="gm-studio-nav-heading">Workspace</p>
      <a href="{{ route('account.overview') }}" @class(['is-current'=>request()->routeIs('account.overview')])
         aria-label="Overview" title="Overview" @if(request()->routeIs('account.overview')) aria-current="page" @endif>
        @include('partials.studio-icon',['name'=>'grid'])<span class="gm-workspace-link-label">Overview</span>
      </a>
      <p class="gm-studio-nav-heading">Your library</p>
      <a href="{{ route('account.library',['section'=>'watchlist']) }}"
         @class(['is-current'=>request()->routeIs('account.library')&&request()->route('section')==='watchlist'])
         aria-label="My List" title="My List">
        @include('partials.studio-icon',['name'=>'heart'])<span class="gm-workspace-link-label">My List</span>
      </a>
      <a href="{{ route('account.library',['section'=>'history']) }}"
         @class(['is-current'=>request()->routeIs('account.library')&&request()->route('section')==='history'])
         aria-label="Watch history" title="Watch history">
        @include('partials.studio-icon',['name'=>'history'])<span class="gm-workspace-link-label">Watch history</span>
      </a>
      <p class="gm-studio-nav-heading">Account</p>
      <a href="{{ route('account.settings') }}" @class(['is-current'=>request()->routeIs('account.settings')])
         aria-label="Settings &amp; profiles" title="Settings &amp; profiles" @if(request()->routeIs('account.settings')) aria-current="page" @endif>
        @include('partials.studio-icon',['name'=>'settings'])<span class="gm-workspace-link-label">Settings &amp; profiles</span>
      </a>
      @if($workspaceIsStaff)
        <p class="gm-studio-nav-heading">Management</p>
        <a href="{{ route('admin.dashboard') }}" aria-label="Management Studio" title="Management Studio">
          @include('partials.studio-icon',['name'=>'grid'])<span class="gm-workspace-link-label">Management Studio</span>
        </a>
      @endif
      <p class="gm-studio-nav-heading">Explore</p>
      <a href="{{ route('browse') }}" aria-label="Explore catalog" title="Explore catalog">
        @include('partials.studio-icon',['name'=>'external'])<span class="gm-workspace-link-label">Explore catalog</span>
      </a>
    @endif
  </nav>
  <div class="gm-studio-identity gm-workspace-sidebar-identity">
    <span class="gm-workspace-sidebar-avatar">@include('partials.account-avatar')</span>
    <span class="gm-workspace-sidebar-identity-label">
      <small>Signed in as</small>
      <strong>{{ auth()->user()->name }}</strong>
    </span>
  </div>
</aside>
