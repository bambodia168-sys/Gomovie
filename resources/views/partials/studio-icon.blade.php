<svg class="gm-nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name ?? '')
@case('home')<path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-7h6v7"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
@case('film')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4m10 0h4M3 15h4m10 0h4"/>@break
@case('series')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>@break
@case('upload')<path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4"/>@break
@case('media')<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 17-5-5L5 21"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 6a3 3 0 0 1 0 6m2 8v-2a5 5 0 0 0-2-4"/>@break
@case('settings')<path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4M18.4 5.6 17 7m-10 10-1.4 1.4"/><circle cx="12" cy="12" r="4"/>@break
@case('appearance')<path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1-4l-.4-1a2 2 0 0 1 2-2H19a3 3 0 0 0 2-3A9 9 0 0 0 12 3z"/><circle cx="7" cy="11" r="1"/><circle cx="9" cy="7" r="1"/><circle cx="15" cy="7" r="1"/>@break
@case('type')<path d="M4 6V4h16v2m-8-2v16m-5 0h10"/>@break
@case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>@break
@case('history')<path d="M3 12a9 9 0 1 1 3 6.7M3 3v6h6m3-2v5l4 2"/>@break
@case('shield')<path d="M12 2 4 5v6c0 6 4 9 8 11 4-2 8-5 8-11V5z"/><path d="m9 12 2 2 4-4"/>@break
@case('external')<path d="M13 4h7v7M20 4l-9 9M20 14v6H4V4h6"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('dots')<circle cx="12" cy="5" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.5" fill="currentColor" stroke="none"/>@break
@case('edit')<path d="m4 17 13-13 3 3-13 13-4 1z"/>@break
@case('eye')<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>@break
@case('trash')<path d="M4 7h16m-14 0 1 14h10l1-14M9 7V4h6v3m-5 4v6m4-6v6"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@default<circle cx="12" cy="12" r="8"/>@endswitch
</svg>