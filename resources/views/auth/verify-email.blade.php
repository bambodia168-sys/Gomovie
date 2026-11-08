@extends('layouts.auth')
@section('title', 'Verify your email')
@section('content')
<h1 class="text-2xl font-bold">Check your inbox</h1>
<p class="mt-3 text-sm leading-6 text-zinc-300">We've sent a secure verification link to your email address. Verify it before accessing your account or the management workspace.</p>
@if(session('status'))<p role="status" class="mt-5 rounded-xl bg-emerald-950/50 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('verification.send') }}" class="mt-7">@csrf
    <button class="w-full rounded-xl bg-[#E50914] px-5 py-3.5 font-semibold hover:bg-red-700" type="submit">Resend verification email</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf
    <button type="submit" class="text-sm text-zinc-400 hover:text-white">Sign out</button>
</form>
@endsection
