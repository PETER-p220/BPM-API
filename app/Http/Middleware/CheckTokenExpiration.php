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
            // Extract the Bearer token from the Authorization header
            $authHeader = $request->header('Authorization');
            
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $tokenString = substr($authHeader, 7); // Remove "Bearer " prefix
                
                // Find the token in the database that matches the current request
                $currentToken = $user->tokens()
                    ->where('token', hash('sha256', $tokenString))
                    ->first();
                
                if ($currentToken) {
                    $tokenCreatedAt = Carbon::parse($currentToken->created_at);
                    $expirationTime = $tokenCreatedAt->addDays(7); // Changed from 8 hours to 7 days
                    
                    if (Carbon::now()->greaterThan($expirationTime)) {
                        // Log the expiration for debugging
                        \Log::warning('Token expired for user ' . $user->user_id . ' at ' . Carbon::now());
                        
                        // Delete only the expired token, not all tokens
                        $currentToken->delete();
                        return response()->json(['message' => 'Token has expired', 'code' => 'TOKEN_EXPIRED'], 401);
                    }
                } else {
                    // Log invalid token attempts for debugging
                    \Log::warning('Invalid token attempt for user ' . $user->user_id . ' at ' . Carbon::now());
                    return response()->json(['message' => 'Invalid token', 'code' => 'INVALID_TOKEN'], 401);
                }
            } else {
                // Log missing authorization header
                \Log::warning('Missing Authorization header for user ' . $user->user_id);
            }
        }

        return $next($request);
    }
}
