<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        // API public catalog endpoints do not require a logged-in user.
        if ($request->user() && !$request->user()->is_active) {
            abort(403, 'Account is suspended.');
        }
        return $next($request);
    }
}
