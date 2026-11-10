{{-- Reusable, permission-safe identity avatar. Only the account owner can fetch the image. --}}
@php
  $avatarUser = $user ?? auth()->user();
  $displayName = (string) ($avatarUser?->name ?? 'User');
  $avatarVersion = $avatarUser?->updated_at?->timestamp ?? 0;
@endphp
@if($avatarUser && filled($avatarUser->avatar_path))
<img class="gm-avatar-img" src="{{ route('account.avatar') }}?v={{ $avatarVersion }}"
     alt="" width="38" height="38" loading="lazy" decoding="async">
@else
<span class="gm-avatar-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
@endif
