@extends('layouts.auth')
@section('title', 'Choose a new password')
@section('content')
<h1 class="text-2xl font-bold">Choose a new password</h1>
<p class="mt-2 text-sm text-zinc-400">Enter your email and set a strong new password.</p>
<form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email','value'=>$email])
    @include('auth.partials.input',['name'=>'password','label'=>'New password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
    @include('auth.partials.input',['name'=>'password_confirmation','label'=>'Confirm new password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
    <button type="submit" class="w-full rounded-xl bg-[#E50914] px-5 py-3.5 font-semibold hover:bg-red-700">Reset password</button>
</form>
@endsection
