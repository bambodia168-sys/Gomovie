<?php

namespace App\Http\Middleware;

use App\Support\AdministratorTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireAdministratorTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs('logout')) {
            return $next($request);
        }

        if (AdministratorTwoFactor::enabled($user)) {
            if (! AdministratorTwoFactor::verified($request, $user)) {
                Auth::guard('web')->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Sign in with two-factor authentication.'], 401)
                    : redirect()->route('login')->with('status', 'Sign in with your two-factor code to continue.');
            }
        } elseif (AdministratorTwoFactor::required($user) && ! $request->routeIs('account.two-factor.*')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Set up two-factor authentication in Account Settings.'], 403)
                : redirect()->route('account.two-factor.show');
        }

        return $next($request);
    }
}
