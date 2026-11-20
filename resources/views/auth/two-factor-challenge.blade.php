@extends('layouts.auth')
@section('title','Two-factor verification')
@section('content')
<h1 class="gm-auth-title">Verify your sign-in</h1>
<p class="gm-auth-subtitle">Enter the six-digit code from your authenticator app, or use a recovery code.</p>
<form method="POST" action="{{ route('two-factor.verify') }}" class="gm-auth-form">
  @csrf
  <div class="gm-auth-field">
    <label for="two_factor_code">Authenticator or recovery code</label>
    <input id="two_factor_code" name="two_factor_code" autocomplete="one-time-code" autocapitalize="none"
      spellcheck="false" maxlength="32" required @error('two_factor_code') aria-invalid="true" aria-describedby="gm-factor-error" @enderror>
    @error('two_factor_code')<p id="gm-factor-error" role="alert" class="gm-field-error">{{ $message }}</p>@enderror
  </div>
  <button type="submit" class="gm-button gm-button-primary gm-auth-submit">Verify and sign in</button>
</form>
<form method="POST" action="{{ route('two-factor.cancel') }}" class="gm-auth-switch">
  @csrf
  <button type="submit" class="underline">Back to sign in</button>
</form>
@endsection
