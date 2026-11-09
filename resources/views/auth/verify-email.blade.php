@extends('layouts.auth')
@section('title',\App\Support\StudioAppearance::text('email-verification','seo_title','Verify your email'))
@section('description',\App\Support\StudioAppearance::text('email-verification','seo_description','Verify your email address with Gomovie.'))
@section('content')
<h1 class="gm-auth-title">{{ \App\Support\StudioAppearance::text('email-verification','heading','Check your inbox') }}</h1>
<p class="gm-auth-subtitle">{{ \App\Support\StudioAppearance::text('email-verification','description','A secure verification link has been sent to your email address. Verify it before accessing your Gomovie account and personal library.') }}</p>
@if(session('status'))<div role="status" class="gm-message">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('verification.send') }}" class="gm-auth-form">
  @csrf
  <button class="gm-button gm-button-primary gm-auth-submit" type="submit">Resend verification email <span aria-hidden="true">→</span></button>
</form>
<form method="POST" action="{{ route('logout') }}" class="gm-auth-switch">
  @csrf
  <button type="submit" class="gm-button gm-button-secondary">Sign out</button>
</form>
@endsection
