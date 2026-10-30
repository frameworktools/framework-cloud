<?php

namespace App\Providers;

use App\Support\Credentials;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fill in this server's credentials saved on the admin setup page,
        // wherever the environment leaves them unset
        Credentials::apply();
    }
}
