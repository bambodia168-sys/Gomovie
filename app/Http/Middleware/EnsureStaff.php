<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(
            $user && $user->is_active && $user->hasVerifiedEmail()
                && in_array($user->role, ['editor', 'admin', 'super_admin'], true),
            403,
        );

        return $next($request);
    }
}
