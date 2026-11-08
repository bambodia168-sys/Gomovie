@extends('layouts.app')
@section('title','Your account')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
  <div><p class="text-xs uppercase tracking-widest text-[#F5C518]">My account</p>
  <h1 class="mt-2 text-3xl font-bold">Welcome, {{ auth()->user()->name }}</h1>
  <p class="mt-2 text-zinc-400">Manage your Gomovie viewer account and profiles.</p></div>
  <span class="rounded-full border border-white/15 bg-zinc-900 px-4 py-2 text-sm capitalize text-zinc-300">{{ auth()->user()->role }}</span>
</div>
<div class="mt-8 grid gap-5 md:grid-cols-3">
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><h2 class="font-semibold">Email verification</h2><p class="mt-3 text-sm text-emerald-300">Verified</p><p class="mt-1 break-all text-sm text-zinc-400">{{ auth()->user()->email }}</p></section>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><h2 class="font-semibold">Viewer profiles</h2><p class="mt-3 text-2xl font-bold">{{ \App\Models\UserProfile::where('user_id',auth()->id())->count() }}</p><p class="mt-1 text-sm text-zinc-400">Personal viewing spaces</p></section>
  <section class="rounded-2xl border border-white/10 bg-zinc-900/70 p-6"><h2 class="font-semibold">Subscription</h2><p class="mt-3 text-sm text-[#F5C518]">Subscription purchases unavailable</p><p class="mt-1 text-sm text-zinc-400">Billing will open only after compliance testing.</p></section>
</div>
@endsection
