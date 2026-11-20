<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdministratorTwoFactor;
use App\Support\StudioNavigation;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;
use XMLWriter;

final class AdministratorTwoFactorController extends Controller
{
    public function show(Request $request, Google2FA $totp): View
    {
        $this->requireAdministrator($request);
        $pending = $this->pending($request);
        $secret = $pending ? Crypt::decryptString($pending['secret']) : null;
        $image = null;
        if ($secret !== null && class_exists(XMLWriter::class)) {
            $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd);
            $uri = $totp->getQRCodeUrl('GoMovie', $request->user()->email, $secret);
            $image = base64_encode((new Writer($renderer))->writeString($uri));
        }

        return view('account.administrator-two-factor', [
            'enabled' => AdministratorTwoFactor::enabled($request->user()),
            'recentlyRecovered' => AdministratorTwoFactor::recentlyRecovered($request, $request->user()),
            'secret' => $secret,
            'image' => $image,
        ]);
    }

    public function begin(Request $request, AdministratorTwoFactor $twoFactor): RedirectResponse
    {
        $this->requireAdministrator($request);
        $user = $request->user();
        $needsCode = AdministratorTwoFactor::enabled($user) && ! AdministratorTwoFactor::recentlyRecovered($request, $user);
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'two_factor_code' => [$needsCode ? 'required' : 'prohibited', 'string', 'max:32'],
        ]);

        if ($needsCode) {
            $key = 'two-factor-replace:'.$user->id;
            $verified = RateLimiter::attempt($key, 6, fn () => $twoFactor->accept($user, $data['two_factor_code']) ?? '', 120);
            if (! $verified instanceof User) {
                throw ValidationException::withMessages(['two_factor_code' => $verified === false
                    ? 'Too many attempts. Wait two minutes and try again.'
                    : 'This code is invalid or has already been used.']);
            }
            RateLimiter::clear($key);
        }

        $request->session()->put(AdministratorTwoFactor::ENROLLMENT, [
            'credential' => AdministratorTwoFactor::credentialFingerprint($user),
            'secret' => Crypt::encryptString($twoFactor->generateSecret()),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('account.two-factor.show');
    }

    public function confirm(Request $request, AdministratorTwoFactor $twoFactor): Response|RedirectResponse
    {
        $this->requireAdministrator($request);
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('account.two-factor.show')->with('status', 'Setup expired. Start again to get a new QR code.');
        }
        $data = $request->validate(['two_factor_code' => ['required', 'digits:6']]);
        $result = $twoFactor->enroll($request->user(), $pending, Crypt::decryptString($pending['secret']), $data['two_factor_code']);
        if (! $result) {
            throw ValidationException::withMessages(['two_factor_code' => 'The code could not be verified. Try the current code from your authenticator.']);
        }

        $request->session()->forget(AdministratorTwoFactor::ENROLLMENT);
        $request->session()->regenerate(true);
        Auth::guard('web')->setUser($result['user']);
        AdministratorTwoFactor::markVerified($request, $result['user']);

        return response()->view('account.two-factor-recovery', ['codes' => $result['codes']]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->requireAdministrator($request);
        $request->session()->forget(AdministratorTwoFactor::ENROLLMENT);

        return redirect()->route('account.two-factor.show');
    }

    private function requireAdministrator(Request $request): void
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(AdministratorTwoFactor::ENROLLMENT);
        if ($pending && $pending['expires'] > now()->timestamp
            && hash_equals($pending['credential'], AdministratorTwoFactor::credentialFingerprint($request->user()))) {
            return $pending;
        }

        $request->session()->forget(AdministratorTwoFactor::ENROLLMENT);

        return null;
    }
}
