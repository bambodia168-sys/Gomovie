<?php

namespace App\Http\Middleware;

use App\Support\StudioNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyGoMovieLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(StudioNavigation::locale());
        return $next($request);
    }
}
