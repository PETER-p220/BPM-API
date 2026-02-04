<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCookieConsent
{
    public function handle(Request $request, Closure $next)
    {
        // If no cookie is found, block access
        if (!$request->cookie('cookie_consent')) {
            return response()->json(['message' => 'Cookies not accepted'], 403);
        }

        return $next($request);
    }
}
