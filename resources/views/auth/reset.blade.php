@extends('layouts.auth')
@section('title',\App\Support\StudioAppearance::text('reset-password','seo_title','Choose a new password'))
@section('description',\App\Support\StudioAppearance::text('reset-password','seo_description','Reset your Gomovie password.'))
@section('content')
<h1 class="gm-auth-title">{{ \App\Support\StudioAppearance::text('reset-password','heading','Choose a new password') }}</h1>
<p class="gm-auth-subtitle">{{ \App\Support\StudioAppearance::text('reset-password','description','Confirm your account email and choose a strong new password.') }}</p>
<form method="POST" action="{{ route('password.update') }}" class="gm-auth-form">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email','value'=>$email])
  @include('auth.partials.input',['name'=>'password','label'=>'New password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
  @include('auth.partials.input',['name'=>'password_confirmation','label'=>'Confirm new password','type'=>'password','autocomplete'=>'new-password','minlength'=>12])
  <p class="gm-form-hint">Choose at least 12 characters, including letters and numbers.</p>
  <button type="submit" class="gm-button gm-button-primary gm-auth-submit">Reset password <span aria-hidden="true">→</span></button>
</form>
<p class="gm-auth-switch"><a href="{{ route('login') }}">Return to sign in</a></p>
@endsection
