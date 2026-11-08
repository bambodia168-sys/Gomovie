@extends('layouts.auth')
@section('title','Sign in')
@section('content')
<h1 class="gm-auth-title">Welcome back</h1>
<p class="gm-auth-subtitle">Sign in to manage your Gomovie account and continue exploring.</p>
@if(session('status'))<div role="status" class="gm-message">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('login') }}" class="gm-auth-form">
  @csrf
  @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
  @include('auth.partials.input',['name'=>'password','label'=>'Password','type'=>'password','autocomplete'=>'current-password'])
  <div class="gm-auth-extras">
    <label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
    <a href="{{ route('password.request') }}">Forgot password?</a>
  </div>
  <button type="submit" class="gm-button gm-button-primary gm-auth-submit">Sign in <span aria-hidden="true">→</span></button>
</form>
<p class="gm-auth-switch">New to Gomovie? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
