@extends('layouts.account')
@section('title','Account Settings')
@section('account_content')
<div class="gm-page-heading"><div><p class="text-xs uppercase tracking-widest text-rose-300">Account & security</p><h1>Settings & profiles</h1><p>Manage your identity, password and personal viewing spaces.</p></div>
<div class="gm-page-actions">
<button type="button" class="gm-studio-button" data-studio-open="gm-identity-dialog">@include('partials.studio-icon',['name'=>'edit']) Edit details</button>
<button type="button" class="gm-studio-button" data-studio-open="gm-password-dialog">@include('partials.studio-icon',['name'=>'shield']) Change password</button>
@if($profiles->count()<5)<button type="button" class="gm-studio-button is-primary" data-studio-open="gm-new-profile-dialog">@include('partials.studio-icon',['name'=>'plus']) Add profile</button>@endif
</div></div>
<div class="grid gap-5 lg:grid-cols-2">
<section class="gm-studio-panel"><div class="gm-studio-panel-head"><div><h3>Personal details</h3><small>Verified account identity</small></div><button type="button" class="gm-studio-button" data-studio-open="gm-identity-dialog">Edit</button></div>
<div class="gm-studio-panel-body"><div class="mb-4"><p class="gm-studio-muted text-xs">Full name</p><p class="mt-1 font-semibold">{{ auth()->user()->name }}</p></div><div><p class="gm-studio-muted text-xs">Email address</p><p class="mt-1 font-semibold break-all">{{ auth()->user()->email }}</p></div><span class="gm-studio-pill {{ auth()->user()->hasVerifiedEmail()?'is-ok':'is-warn' }} mt-4">{{ auth()->user()->hasVerifiedEmail()?'Verified email':'Pending verification' }}</span></div>
</section>
<section class="gm-studio-panel"><div class="gm-studio-panel-head"><div><h3>Security</h3><small>Password and sign-in protection</small></div><button type="button" class="gm-studio-button" data-studio-open="gm-password-dialog">Update</button></div>
<div class="gm-studio-panel-body"><p class="text-sm text-zinc-300">Use at least 12 characters with letters and numbers.</p><p class="text-sm mt-3 gm-studio-muted">Changing the password revokes existing API tokens.</p></div>
</section>
</div>
<section class="gm-studio-panel mt-6">
<div class="gm-studio-panel-head"><div><h3>Viewing profiles</h3><small>Manage up to five profiles. Kids mode does not enable unrestricted playback.</small></div><span class="gm-studio-pill">{{ $profiles->count() }} / 5</span></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table"><thead><tr><th>No.</th><th>Profile</th><th>Mode</th><th class="text-right">Actions</th></tr></thead><tbody>
@foreach($profiles as $profile)
<tr><td>{{ $loop->iteration }}</td><td>{{ $profile->name }}</td><td><span class="gm-studio-pill {{ $profile->is_kids?'is-warn':'is-ok' }}">{{ $profile->is_kids?'Kids · restricted':'Standard' }}</span></td>
<td class="text-right"><button class="gm-studio-dots" type="button" aria-label="Actions for {{ $profile->name }}" aria-expanded="false" data-studio-menu="profile-menu-{{ $profile->id }}">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="profile-menu-{{ $profile->id }}">
<button role="menuitem" type="button" data-studio-open="gm-profile-dialog-{{ $profile->id }}">@include('partials.studio-icon',['name'=>'edit']) Edit profile</button>
@if($profiles->count()>1)
<form action="{{ route('account.profiles.destroy',$profile->id) }}" method="POST">@csrf @method('DELETE')
<button type="submit" role="menuitem" class="is-danger" data-studio-confirm="Delete this profile and its watch history?">@include('partials.studio-icon',['name'=>'trash']) Delete profile</button>
</form>@endif
</template></td></tr>
@endforeach
</tbody></table></div>
</section>
<dialog class="gm-studio-modal" id="gm-identity-dialog" aria-labelledby="gm-identity-heading">
<form method="POST" action="{{ route('account.identity') }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-identity-heading">Edit personal details</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Full name</span><input class="gm-studio-input" name="name" value="{{ old('name',auth()->user()->name) }}" maxlength="100" required autocomplete="name"></label>
<label class="gm-studio-field"><span>Email address</span><input class="gm-studio-input" name="email" type="email" value="{{ old('email',auth()->user()->email) }}" required autocomplete="email"></label>
<p class="text-xs gm-studio-muted">Changing your email requires verification of the new address.</p>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Save details</button></div></form></dialog>
<dialog class="gm-studio-modal" id="gm-password-dialog" aria-labelledby="gm-password-heading">
<form method="POST" action="{{ route('account.password') }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-password-heading">Change password</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<label class="gm-studio-field"><span>Current password</span><input class="gm-studio-input" name="current_password" type="password" required autocomplete="current-password"></label>
<label class="gm-studio-field"><span>New password</span><input class="gm-studio-input" name="password" type="password" minlength="12" required autocomplete="new-password"></label>
<label class="gm-studio-field"><span>Confirm new password</span><input class="gm-studio-input" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Update password</button></div></form></dialog>
@if($profiles->count()<5)
<dialog class="gm-studio-modal" id="gm-new-profile-dialog" aria-labelledby="gm-new-profile-heading">
<form action="{{ route('account.profiles.store') }}" method="POST">@csrf
<div class="gm-studio-modal-heading"><h3 id="gm-new-profile-heading">Add viewing profile</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body"><label class="gm-studio-field"><span>Profile name</span><input class="gm-studio-input" name="name" maxlength="80" required></label><label class="flex items-center gap-2 text-sm"><input class="gm-studio-input" type="checkbox" name="is_kids" value="1"> Kids profile (restricted playback)</label></div>
<div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Add profile</button></div></form></dialog>
@endif
@foreach($profiles as $profile)
<dialog class="gm-studio-modal" id="gm-profile-dialog-{{ $profile->id }}" aria-labelledby="gm-profile-heading-{{ $profile->id }}">
<form action="{{ route('account.profiles.update',$profile->id) }}" method="POST">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-profile-heading-{{ $profile->id }}">Edit {{ $profile->name }}</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body"><label class="gm-studio-field"><span>Profile name</span><input class="gm-studio-input" name="name" value="{{ $profile->name }}" maxlength="80" required></label><label class="flex items-center gap-2 text-sm"><input class="gm-studio-input" type="checkbox" name="is_kids" value="1" @checked($profile->is_kids)> Kids profile (restricted playback)</label></div>
<div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary">Save profile</button></div></form></dialog>
@endforeach
@endsection
