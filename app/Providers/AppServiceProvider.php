<?php

namespace App\Providers;

use App\Support\Credentials;
use Illuminate\Support\Facades\Vite;
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
        // An empty public/hot (left by a dev server that didn't finish
        // starting) would send every page to a dev server that isn't there.
        // Use the built assets instead.
        $hot = public_path('hot');
        if (is_file($hot) && filesize($hot) === 0) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }

        // Fill in this server's credentials saved on the admin setup page,
        // wherever the environment leaves them unset
        Credentials::apply();
    }
}
