<?php

namespace App\Http\Controllers;

use App\Support\StudioNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

final class LocaleController extends Controller
{
    public function change(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(StudioNavigation::LANGUAGES))],
        ]);
        $locale = $values['locale'];
        $request->session()->put('gomovie.locale', $locale);

        // The return target is always the current application's own origin.
        // Do not redirect to a forged Referer or an external URL.
        $previous = URL::previous();
        $path = parse_url($previous, PHP_URL_PATH);
        $host = parse_url($previous, PHP_URL_HOST);
        $query = parse_url($previous, PHP_URL_QUERY);
        if (is_string($path) && is_string($host)
            && hash_equals($request->getHost(), $host)
            && str_starts_with($path, '/') && !str_starts_with($path, '//')
            && !str_starts_with($path, '/language')) {
            return redirect($path.(is_string($query) && strlen($query) < 1000 ? '?'.$query : ''), 303);
        }
        return redirect()->route('home', [], 303);
    }
}
