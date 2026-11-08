@extends('layouts.auth')
@section('title', 'Create account')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Create your account</h1>
<p class="mt-2 text-sm text-zinc-400">Join Gomovie. Your account begins with free viewer access.</p>
<form method="POST" action="{{ route('register') }}" class="mt-7 space-y-4">
    @csrf
    @include('auth.partials.input',['name'=>'name','label'=>'Full name','autocomplete'=>'name'])
    @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
    @include('auth.partials.input',['name'=>'password','label'=>'Password (12+ characters)','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
    @include('auth.partials.input',['name'=>'password_confirmation','label'=>'Confirm password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
    <p class="text-xs leading-5 text-zinc-500">Use at least 12 characters with letters and numbers. Verify your email before accessing your account.</p>
    <button class="w-full rounded-xl bg-[#E50914] px-5 py-3.5 font-semibold hover:bg-red-700" type="submit">Create account</button>
</form>
<p class="mt-6 text-center text-sm text-zinc-400">Already registered? <a href="{{ route('login') }}" class="font-semibold text-white hover:text-[#F5C518]">Sign in</a></p>
@endsection
