<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AdministratorTwoFactor;
use App\Support\StudioNavigation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function loginForm(Request $request): View
    {
        $request->session()->forget(AdministratorTwoFactor::CHALLENGE);
        $candidate = $request->query('return');
        if ($return = StudioNavigation::watchReturn(is_string($candidate) ? $candidate : null)) {
            $request->session()->put('gomovie.watchReturn', $return);
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $request->session()->forget(AdministratorTwoFactor::CHALLENGE);
        $pending = null;
        $ok = Auth::guard('web')->attemptWhen([
            'email' => Str::lower($credentials['email']),
            'password' => $credentials['password'],
            'is_active' => true,
        ], function (User $user) use (&$pending): bool {
            if (! AdministratorTwoFactor::enabled($user)) {
                return true;
            }
            $pending = $user;

            return false;
        }, (bool) ($credentials['remember'] ?? false));

        if ($pending) {
            $request->session()->regenerate(true);
            $candidate = $request->input('return');
            if ($return = StudioNavigation::watchReturn(is_string($candidate) ? $candidate : null)) {
                $request->session()->put('gomovie.watchReturn', $return);
            }
            $request->session()->put(AdministratorTwoFactor::CHALLENGE, [
                'user' => $pending->id,
                'credential' => AdministratorTwoFactor::credentialFingerprint($pending),
                'expires' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        if (!$ok) {
            return back()->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        return $this->completeLogin($request);
    }

    public function challengeForm(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request)
            ? view('auth.two-factor-challenge')
            : redirect()->route('login');
    }

    public function challenge(Request $request, AdministratorTwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('status', 'Your sign-in expired. Enter your password again.');
        }
        $data = $request->validate(['two_factor_code' => ['required', 'string', 'max:32']]);
        $key = 'two-factor-sign-in:'.$user->id;
        $verified = RateLimiter::attempt($key, 6, fn () => $twoFactor->accept($user, $data['two_factor_code']) ?? '', 120);
        if (! $verified instanceof User) {
            return back()->withErrors(['two_factor_code' => $verified === false
                ? 'Too many attempts. Wait two minutes and try again.'
                : 'This code is invalid or has already been used.']);
        }
        RateLimiter::clear($key);
        $request->session()->forget(AdministratorTwoFactor::CHALLENGE);
        Auth::guard('web')->login($verified);
        AdministratorTwoFactor::markVerified($request, $verified);
        if (! preg_match('/^\d{6}$/D', $data['two_factor_code'])) {
            AdministratorTwoFactor::recordRecoverySignIn($request, $verified);
        }

        return $this->completeLogin($request);
    }

    public function cancelChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget(AdministratorTwoFactor::CHALLENGE);

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(AdministratorTwoFactor::CHALLENGE);
        if ($pending && $pending['expires'] > now()->timestamp) {
            $user = User::find($pending['user']);
            if ($user?->is_active && AdministratorTwoFactor::enabled($user)
                && hash_equals($pending['credential'], AdministratorTwoFactor::credentialFingerprint($user))) {
                return $user;
            }
        }
        $request->session()->forget(AdministratorTwoFactor::CHALLENGE);

        return null;
    }

    private function completeLogin(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        // Only allow the known on-site watch route as an explicit return URL.
        // Otherwise send users to the verified account appropriate to their role.
        $candidate = $request->input('return');
        $return = StudioNavigation::watchReturn(is_string($candidate) ? $candidate : null)
            ?? StudioNavigation::watchReturn($request->session()->get('gomovie.watchReturn'));
        if (!$request->user()->hasVerifiedEmail()) {
            if ($return) $request->session()->put('gomovie.watchReturn', $return);
            return redirect()->route('verification.notice');
        }
        $request->session()->forget('gomovie.watchReturn');
        return $return
            ? redirect($return)
            : redirect(StudioNavigation::landing($request->user()));
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'], 'email' => Str::lower($data['email']),
                'password' => $data['password'],
            ]);
            UserProfile::create([
                'user_id' => $user->id, 'name' => 'Main', 'slug' => 'main',
                'is_kids' => false,
            ]);
            return $user;
        });
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    public function verificationNotice(): View|RedirectResponse
    {
        return request()->user()->hasVerifiedEmail()
            ? redirect(StudioNavigation::landing(request()->user()))
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();
        $return = StudioNavigation::watchReturn($request->session()->pull('gomovie.watchReturn'));
        return redirect($return ?? StudioNavigation::landing($request->user()))
            ->with('status', 'Your email is verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (!$request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }
        return back()->with('status', 'Verification link sent if your email requires verification.');
    }

    public function forgotForm(): View
    {
        return view('auth.forgot');
    }

    public function forgot(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email']]);
        // Never reveal whether an account exists.
        Password::sendResetLink(['email' => Str::lower($data['email'])]);
        return back()->with('status', 'If the account exists, a reset link will be sent.');
    }

    public function resetForm(string $token, Request $request): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return redirect()->route('login')->with('status', 'Your password was reset. Sign in to continue.');
    }
}
