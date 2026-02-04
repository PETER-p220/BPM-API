<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class CookieController extends Controller
{
    public function acceptCookies()
    {
        return response()->json(['message' => 'Cookies accepted'])
            ->cookie('cookie_consent', true, 60 * 24 * 30); // 30 days
    }
}
