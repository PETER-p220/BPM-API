<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;

echo "=== Debugging Authentication Tokens ===\n";

// Count all tokens
$totalTokens = PersonalAccessToken::count();
echo "Total tokens in database: {$totalTokens}\n";

// Count expired tokens
$expiredTokens = PersonalAccessToken::where('expires_at', '<', now())->count();
echo "Expired tokens: {$expiredTokens}\n";

// Show recent tokens
$recentTokens = PersonalAccessToken::with('tokenable')->orderBy('created_at', 'desc')->limit(5)->get();

echo "\nRecent tokens:\n";
foreach ($recentTokens as $token) {
    $user = $token->tokenable;
    echo "- User: {$user->name} ({$user->email})\n";
    echo "  Created: {$token->created_at}\n";
    echo "  Expires: {$token->expires_at}\n";
    echo "  Expired: " . ($token->expires_at && now()->greaterThan($token->expires_at) ? 'Yes' : 'No') . "\n";
    echo "  Abilities: " . implode(', ', $token->abilities) . "\n\n";
}

// Clean up expired tokens
if ($expiredTokens > 0) {
    echo "Cleaning up expired tokens...\n";
    PersonalAccessToken::where('expires_at', '<', now())->delete();
    echo "Expired tokens deleted.\n";
}

echo "=== Debug Complete ===\n";
