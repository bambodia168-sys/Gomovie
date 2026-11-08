@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
<p class="mt-2 text-sm text-zinc-400">Sign in to continue watching and managing your list.</p>
@if(session('status'))<p class="mt-4 rounded-xl border border-emerald-700/50 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
    @csrf
    @include('auth.partials.input', ['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
    @include('auth.partials.input', ['name'=>'password','label'=>'Password','type'=>'password','autocomplete'=>'current-password'])
    <div class="flex items-center justify-between gap-2 text-sm">
        <label class="inline-flex items-center gap-2 text-zinc-400"><input class="accent-[#E50914]" type="checkbox" name="remember" value="1"> Remember me</label>
        <a class="text-[#F5C518] hover:underline" href="{{ route('password.request') }}">Forgot password?</a>
    </div>
    <button type="submit" class="w-full rounded-xl bg-[#E50914] px-5 py-3.5 font-semibold text-white transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#F5C518]">Sign in</button>
</form>
<p class="mt-6 text-center text-sm text-zinc-400">New to Gomovie? <a href="{{ route('register') }}" class="font-semibold text-white hover:text-[#F5C518]">Create account</a></p>
@endsection
