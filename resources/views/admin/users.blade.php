@extends('layouts.admin')
@section('title','User Manager')
@section('content')
<div class="gm-page-heading"><div><p class="text-xs uppercase tracking-widest text-amber-300">Administration</p><h2>User Manager</h2><p>Manage verified users and access permissions. Super-admin promotion remains server-only.</p></div>
<div class="gm-page-actions"><a class="gm-studio-button" href="{{ route('admin.dashboard') }}">@include('partials.studio-icon',['name'=>'grid']) Dashboard</a></div></div>
<section class="gm-studio-panel">
<div class="gm-studio-panel-head"><div><h3>Member directory</h3><small>10 items per page</small></div>
<form method="GET" class="gm-page-actions" action="{{ route('admin.users.index') }}">
<label for="user-search" class="sr-only">Search members</label><input class="gm-studio-input" id="user-search" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search members…">
<button class="gm-studio-button" type="submit">Search</button></form></div>
<div class="gm-studio-table-scroll"><table class="gm-studio-table">
<thead><tr><th>No.</th><th>Member</th><th>Email verified</th><th>Role</th><th>Account</th><th class="text-right">Actions</th></tr></thead>
<tbody>@forelse($users as $user)
<tr><td>{{ ($users->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $user->name }}</strong><small class="block mt-1 gm-studio-muted">{{ $user->email }}</small></td>
<td><span class="gm-studio-pill {{ $user->email_verified_at?'is-ok':'is-warn' }}">{{ $user->email_verified_at?'Verified':'Pending' }}</span></td>
<td class="capitalize">{{ str_replace('_',' ',$user->role) }}</td>
<td><span class="gm-studio-pill {{ $user->is_active?'is-ok':'is-warn' }}">{{ $user->is_active?'Active':'Suspended' }}</span></td>
<td class="text-right">
@if(auth()->user()->role==='super_admin' && $user->role!=='super_admin' && $user->id!==auth()->id())
<button type="button" class="gm-studio-dots" data-studio-menu="member-menu-{{ $user->id }}" aria-label="Actions for {{ $user->name }}" aria-expanded="false">@include('partials.studio-icon',['name'=>'dots'])</button>
<template id="member-menu-{{ $user->id }}"><button role="menuitem" type="button" data-studio-open="member-dialog-{{ $user->id }}">@include('partials.studio-icon',['name'=>'edit']) Edit access</button></template>
@else<span class="gm-studio-muted">Protected</span>@endif</td></tr>
@empty<tr><td colspan="6" class="gm-studio-empty">No matching users.</td></tr>@endforelse
</tbody></table></div>
@include('partials.studio-pager',['paginator'=>$users,'id'=>'users-go'])
</section>
@foreach($users as $user)
@if(auth()->user()->role==='super_admin' && $user->role!=='super_admin' && $user->id!==auth()->id())
<dialog class="gm-studio-modal" id="member-dialog-{{ $user->id }}" aria-labelledby="member-heading-{{ $user->id }}">
<form method="POST" action="{{ route('admin.users.update',$user) }}">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="member-heading-{{ $user->id }}">Manage {{ $user->name }}</h3><button class="gm-studio-button" type="button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<p class="mb-5 text-sm gm-studio-muted">{{ $user->email }}</p>
<label class="gm-studio-field"><span>Role</span><select class="gm-studio-input" name="role">@foreach(['viewer','editor','admin'] as $role)<option value="{{ $role }}" @selected($user->role===$role)>{{ ucfirst($role) }}</option>@endforeach</select></label>
<label class="gm-studio-field"><span>Account status</span><select name="is_active" class="gm-studio-input"><option value="1" @selected($user->is_active)>Active</option><option value="0" @selected(!$user->is_active)>Suspended</option></select></label>
<p class="text-xs gm-studio-muted">Changes are recorded in the administrative audit log.</p>
</div><div class="gm-studio-modal-actions"><button class="gm-studio-button" type="button" data-studio-close>Cancel</button><button class="gm-studio-button is-primary" type="submit">Save access</button></div></form>
</dialog>
@endif
@endforeach
@endsection
