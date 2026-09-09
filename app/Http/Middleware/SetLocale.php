<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales');
        $user = $request->user();
        $cookieLocale = $request->cookie('locale');
        $cookieValid = $cookieLocale && in_array($cookieLocale, $available, true);

        if ($user && $user->locale === null && $cookieValid) {
            $user->update(['locale' => $cookieLocale]);
        }

        $effective = $user?->locale ?? ($cookieValid ? $cookieLocale : config('app.locale'));

        app()->setLocale($effective);

        return $next($request);
    }
}
