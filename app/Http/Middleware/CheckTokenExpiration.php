<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $tokenCreatedAt = Carbon::parse($user->tokens()->first()->created_at);
            $expirationTime = $tokenCreatedAt->addHours(8);

            if (Carbon::now()->greaterThan($expirationTime)) {
                $user->tokens()->delete();
                return response()->json(['message' => 'Token has expired'], 401);
            }
        }

        return $next($request);
    }
}
