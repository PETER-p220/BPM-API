<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

echo "Google Redirect URL: " . Socialite::driver('google')->stateless()->redirect()->getTargetUrl() . "\n";
