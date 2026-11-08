@extends('layouts.app')
@section('title','Account settings')
@section('content')
<div class="mb-8"><p class="text-xs font-bold uppercase tracking-widest text-[#F5C518]">My account</p>
<h1 class="mt-2 text-4xl font-black">Settings & profiles</h1>
<p class="mt-3 text-sm text-zinc-400">Manage your identity, sign-in security, and viewing spaces.</p></div>
@if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-red-700/50 bg-red-950/40 p-5 text-sm text-red-200"><p class="font-semibold">Please review these fields:</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6">
    <h2 class="text-xl font-semibold">Personal details</h2>
    <form method="POST" action="{{ route('account.identity') }}" class="mt-6 space-y-4">@csrf @method('PATCH')
      <div><label for="account-name" class="mb-2 block text-sm text-zinc-300">Full name</label><input id="account-name" name="name" value="{{ old('name',auth()->user()->name) }}" maxlength="100" required autocomplete="name" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 outline-none focus:border-[#E50914]"></div>
      <div><label for="account-email" class="mb-2 block text-sm text-zinc-300">Email address</label><input id="account-email" name="email" type="email" value="{{ old('email',auth()->user()->email) }}" required autocomplete="email" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 outline-none focus:border-[#E50914]"></div>
      <p class="text-xs text-zinc-500">Changing your email requires verification of the new address.</p>
      <button type="submit" class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold hover:bg-red-700">Save details</button>
    </form>
  </section>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6">
    <h2 class="text-xl font-semibold">Change password</h2>
    <form method="POST" action="{{ route('account.password') }}" class="mt-6 space-y-4">@csrf @method('PATCH')
      <div><label for="current-password" class="mb-2 block text-sm text-zinc-300">Current password</label><input id="current-password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
      <div><label for="new-password" class="mb-2 block text-sm text-zinc-300">New password</label><input id="new-password" name="password" type="password" minlength="12" required autocomplete="new-password" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
      <div><label for="confirm-password" class="mb-2 block text-sm text-zinc-300">Confirm new password</label><input id="confirm-password" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password" class="w-full rounded-xl border border-white/15 bg-zinc-950 px-4 py-3"></div>
      <p class="text-xs text-zinc-500">At least 12 characters with letters and numbers; existing API tokens will be revoked.</p>
      <button type="submit" class="rounded-xl bg-[#E50914] px-5 py-3 text-sm font-semibold hover:bg-red-700">Update password</button>
    </form>
  </section>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6 lg:col-span-2">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold">Viewing profiles</h2><p class="mt-2 text-xs text-zinc-500">Maximum five profiles. Kids mode remains playback-restricted pending parental controls.</p></div><span class="rounded-full border border-white/15 px-3 py-1 text-xs text-zinc-300">{{ $profiles->count() }} / 5</span></div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      @foreach($profiles as $profile)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-zinc-950/60 p-4">
          <div class="min-w-0"><p class="truncate font-semibold">{{ $profile->name }}</p><p class="mt-1 text-xs text-zinc-500">{{ $profile->is_kids ? 'Kids profile · streaming disabled' : 'Standard profile' }}</p></div>
          @if($profiles->count()>1)<form method="POST" action="{{ route('account.profiles.destroy',$profile->id) }}" onsubmit="return confirm('Delete this profile and its watch history?');">@csrf @method('DELETE')<button class="rounded-lg border border-red-500/20 px-3 py-2 text-xs text-red-300 hover:bg-red-950/30">Remove</button></form>@endif
        </div>
      @endforeach
    </div>
    @if($profiles->count()<5)
      <form action="{{ route('account.profiles.store') }}" method="POST" class="mt-6 flex flex-wrap items-center gap-3">@csrf
        <label class="sr-only" for="profile-name">Profile name</label>
        <input id="profile-name" name="name" maxlength="80" required placeholder="New profile name" class="min-w-48 flex-1 rounded-xl border border-white/15 bg-zinc-950 px-4 py-3 text-sm">
        <label class="inline-flex items-center gap-2 text-sm text-zinc-300"><input type="checkbox" name="is_kids" value="1" class="accent-[#E50914]"> Kids profile</label>
        <button type="submit" class="rounded-xl border border-[#F5C518]/30 px-5 py-3 text-sm font-semibold text-[#F5C518] hover:bg-white/5">Add profile</button>
      </form>
    @endif
  </section>
</div>
@endsection
