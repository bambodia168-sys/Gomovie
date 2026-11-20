@extends('layouts.account')
@section('title','Two-factor authentication')
@section('account_content')
<div class="gm-page-heading">
  <div><h1>Two-factor authentication</h1><p>Protect your administrator account with an authenticator app.</p></div>
  @if(! config('gomovie.administrator_two_factor_required') || $enabled)
    <div class="gm-page-actions"><a class="gm-studio-button" href="{{ route('account.settings') }}">Account settings</a></div>
  @endif
</div>
<section class="gm-studio-panel max-w-2xl" aria-labelledby="gm-factor-setup">
  <div class="gm-studio-panel-head">
    <h3 id="gm-factor-setup">{{ $secret ? 'Connect your authenticator' : 'Authenticator app' }}</h3>
    <span class="gm-studio-pill {{ $enabled ? 'is-ok' : 'is-warn' }}">{{ $enabled ? 'Enabled' : 'Not set up' }}</span>
  </div>
  <div class="gm-studio-panel-body">
    @if($secret)
      @if($image)
      <p class="text-sm gm-studio-muted">Scan this QR code in your authenticator app. You can also enter the setup key manually.</p>
      <img class="my-4 rounded-lg bg-white" width="220" height="220" src="data:image/svg+xml;base64,{{ $image }}" alt="Authenticator setup QR code">
      @else
      <p class="text-sm gm-studio-muted">Enter this setup key in your authenticator app and choose time-based codes.</p>
      @endif
      <p class="mt-3 text-sm">Setup key</p>
      <code class="mt-2 block break-all rounded-lg border p-3 text-sm">{{ $secret }}</code>
      <form method="POST" action="{{ route('account.two-factor.confirm') }}" class="mt-5 space-y-4">
        @csrf
        <label class="gm-studio-field">
          <span>Six-digit code</span>
          <input name="two_factor_code" class="gm-studio-input" inputmode="numeric" autocomplete="one-time-code"
            pattern="[0-9]{6}" maxlength="6" required>
        </label>
        @error('two_factor_code')<p class="gm-field-error" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="gm-studio-button is-primary">Verify and save</button>
      </form>
      <form method="POST" action="{{ route('account.two-factor.cancel') }}" class="mt-3">
        @csrf
        <button type="submit" class="gm-studio-button">Cancel setup</button>
      </form>
    @else
      <p class="text-sm gm-studio-muted">{{ $enabled
        ? 'Use this form when changing phones or when you need new recovery codes. Your previous setup will expire after you verify the new authenticator.'
        : 'You’ll use a six-digit code at sign-in. Keep your recovery codes somewhere safe so you can sign in if you lose your phone.' }}</p>
      @if($recentlyRecovered)
        <p class="mt-3 text-sm gm-studio-muted">You signed in with a recovery code. Confirm your password to connect a new authenticator.</p>
      @endif
      <form method="POST" action="{{ route('account.two-factor.begin') }}" class="mt-5 space-y-4">
        @csrf
        <label class="gm-studio-field">
          <span>Current password</span>
          <input type="password" name="current_password" class="gm-studio-input" autocomplete="current-password" required>
        </label>
        @error('current_password')<p class="gm-field-error" role="alert">{{ $message }}</p>@enderror
        @if($enabled && !$recentlyRecovered)
          <label class="gm-studio-field">
            <span>Authenticator or recovery code</span>
            <input name="two_factor_code" class="gm-studio-input" autocomplete="one-time-code" maxlength="32" required>
          </label>
          @error('two_factor_code')<p class="gm-field-error" role="alert">{{ $message }}</p>@enderror
        @endif
        <button type="submit" class="gm-studio-button is-primary">{{ $enabled ? 'Replace authenticator' : 'Set up authenticator' }}</button>
      </form>
    @endif
  </div>
</section>
@endsection
