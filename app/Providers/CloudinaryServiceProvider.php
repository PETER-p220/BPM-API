<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Cloudinary\Cloudinary;

class CloudinaryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Cloudinary::class, function ($app) {
            return new Cloudinary([
                'cloud' => [
                    'name' => env('CLOUDINARY_CLOUD_NAME'),
                    'key' => env('CLOUDINARY_API_KEY'),
                    'secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => [
                    'secure' => true, // Use HTTPS
                ],
            ]);
        });
    }

    public function boot()
    {
        //
    }
}
