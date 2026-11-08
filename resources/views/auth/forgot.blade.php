@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<h1 class="text-2xl font-bold">Forgot password?</h1>
<p class="mt-2 text-sm text-zinc-400">Enter your account email. We'll send reset instructions if the account exists.</p>
@if(session('status'))<p role="status" class="mt-5 rounded-xl bg-emerald-950/60 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
    @csrf
    @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
    <button class="w-full rounded-xl bg-[#E50914] px-5 py-3.5 font-semibold hover:bg-red-700" type="submit">Send reset link</button>
</form>
<a href="{{ route('login') }}" class="mt-6 block text-center text-sm text-[#F5C518]">Return to sign in</a>
@endsection
