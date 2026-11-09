@extends('layouts.auth')
@section('title','Create your account')
@section('content')
<h1 class="gm-auth-title">{{ \App\Support\StudioAppearance::text('register','heading','Create your account') }}</h1>
<p class="gm-auth-subtitle">{{ \App\Support\StudioAppearance::text('register','description','Join Gomovie and build your own movie and series watchlist.') }}</p>
<form method="POST" action="{{ route('register') }}" class="gm-auth-form">
  @csrf
  @include('auth.partials.input',['name'=>'name','label'=>'Full name','autocomplete'=>'name'])
  @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
  @include('auth.partials.input',['name'=>'password','label'=>'Password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
  @include('auth.partials.input',['name'=>'password_confirmation','label'=>'Confirm password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
  <p class="gm-form-hint">Use at least 12 characters, including letters and numbers. Email verification is required.</p>
  <button type="submit" class="gm-button gm-button-primary gm-auth-submit">Create account <span aria-hidden="true">→</span></button>
</form>
<p class="gm-auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
