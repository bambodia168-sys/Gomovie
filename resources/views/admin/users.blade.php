@extends('layouts.admin')
@section('title','User Manager')
@section('content')
<h2 class="text-3xl font-bold">Member administration</h2>
<p class="mt-2 text-sm text-zinc-400">Only the server operator can promote a verified account to super admin. Account changes are audited.</p>
<div class="mt-7 overflow-x-auto rounded-2xl border border-white/10 bg-zinc-900/60">
<table class="w-full min-w-[800px] text-left text-sm"><thead class="border-b border-white/10 text-xs uppercase text-zinc-500"><tr><th class="p-5">Member</th><th class="p-5">Email verified</th><th class="p-5">Role</th><th class="p-5">Account</th><th class="p-5 text-right">Actions</th></tr></thead>
<tbody class="divide-y divide-white/10">@foreach($users as $user)
<tr><td class="p-5"><div class="font-semibold">{{ $user->name }}</div><div class="mt-1 text-xs text-zinc-500">{{ $user->email }}</div></td><td class="p-5">{{ $user->email_verified_at ? 'Yes' : 'No' }}</td>
@if(auth()->user()->role==='super_admin' && $user->role!=='super_admin')
<td class="p-5"><select class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2" name="role" form="manage-user-{{ $user->id }}">@foreach(['viewer','editor','admin'] as $role)<option value="{{ $role }}" @selected($user->role===$role)>{{ ucfirst($role) }}</option>@endforeach</select></td>
<td class="p-5"><select name="is_active" form="manage-user-{{ $user->id }}" class="rounded-lg border border-white/15 bg-zinc-950 px-3 py-2"><option value="1" @selected($user->is_active)>Active</option><option value="0" @selected(!$user->is_active)>Suspended</option></select></td>
<td class="p-5 text-right"><form id="manage-user-{{ $user->id }}" action="{{ route('admin.users.update',$user) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="rounded-lg bg-[#E50914] px-4 py-2 text-xs font-semibold">Save</button></form></td>
@else
<td class="p-5 capitalize text-zinc-300">{{ str_replace('_',' ',$user->role) }}</td><td class="p-5">{{ $user->is_active ? 'Active' : 'Suspended' }}</td><td class="p-5 text-right text-zinc-500">Protected</td>
@endif
</tr>@endforeach</tbody></table></div>
<div class="mt-6">{{ $users->links() }}</div>
@endsection
