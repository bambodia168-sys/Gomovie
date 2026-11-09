@extends('layouts.auth')
@section('title',\App\Support\StudioAppearance::text('forgot-password','seo_title','Reset password'))
@section('content')
<h1 class="gm-auth-title">{{ \App\Support\StudioAppearance::text('forgot-password','heading','Forgot password?') }}</h1>
<p class="gm-auth-subtitle">{{ \App\Support\StudioAppearance::text('forgot-password','description',"Enter your account email. We'll send reset instructions if the account exists.") }}</p>
@if(session('status'))<div role="status" class="gm-message">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" class="gm-auth-form">
  @csrf
  @include('auth.partials.input',['name'=>'email','label'=>'Email address','type'=>'email','autocomplete'=>'email'])
  <button type="submit" class="gm-button gm-button-primary gm-auth-submit">Send reset link <span aria-hidden="true">→</span></button>
</form>
<p class="gm-auth-switch"><a href="{{ route('login') }}">Return to sign in</a></p>
@endsection
