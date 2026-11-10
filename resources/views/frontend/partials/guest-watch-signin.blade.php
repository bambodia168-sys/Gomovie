{{-- Guest watch gate. A successful login returns to this exact on-site title;
     playback still requires published, approved media and normal entitlements. --}}
<div class="gm-guest-watch" x-data="{ open:false }" @keydown.escape.window="open=false">
  <div class="gm-player-lock-panel gm-guest-watch-card">
    <span class="gm-player-lock-icon" aria-hidden="true">▶</span>
    <p class="gm-eyebrow">{{ $title->is_premium ? 'Premium title' : 'Free title' }}</p>
    <h2>Sign in to watch</h2>
    <p>Use your Go Movie account to continue with {{ $title->title }}. Playback is available only for approved media and valid access rights.</p>
    <button type="button" class="gm-button gm-button-primary"
            @click="$refs.watchLogin.showModal()" aria-haspopup="dialog">Sign In to Watch</button>
    <a href="{{ route('register') }}" class="gm-button gm-button-secondary">Create Account</a>
  </div>
  <dialog x-ref="watchLogin" class="gm-watch-login-dialog"
          aria-labelledby="gm-watch-login-title"
          @click.self="$refs.watchLogin.close()"
          x-init="@if($errors->any()) $nextTick(() => $refs.watchLogin.showModal()) @endif">
    <div class="gm-watch-login-content">
      <button type="button" class="gm-watch-login-close" aria-label="Close sign in"
              @click="$refs.watchLogin.close()">×</button>
      <img src="{{ asset('apple-touch-icon.png') }}" width="54" height="54" alt="" class="gm-watch-login-mark">
      <h2 id="gm-watch-login-title">Sign in to continue watching</h2>
      <p class="gm-watch-login-description">{{ $title->title }} · Your viewing will resume on this page after sign-in.</p>
      @if($errors->any())
        <div role="alert" class="gm-watch-login-error">
          {{ $errors->first('email') ?: $errors->first('password') ?: 'Please review your sign-in details.' }}
        </div>
      @endif
      <form method="POST" action="{{ route('login') }}" class="gm-watch-login-form">
        @csrf
        <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
        <label for="gm-watch-login-email">Email address</label>
        <input id="gm-watch-login-email" name="email" type="email" autocomplete="email"
               value="{{ old('email') }}" maxlength="255" required>
        <label for="gm-watch-login-password">Password</label>
        <input id="gm-watch-login-password" name="password" type="password"
               autocomplete="current-password" required>
        <label class="gm-watch-login-remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button type="submit" class="gm-button gm-button-primary">Sign In</button>
      </form>
      <div class="gm-watch-login-links">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        <a href="{{ route('login',['return'=>request()->getRequestUri()]) }}">Full sign-in page</a>
      </div>
      <p class="gm-watch-login-note">Login does not automatically authorize unavailable or premium video.</p>
    </div>
  </dialog>
</div>
