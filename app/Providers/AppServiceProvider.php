<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        URL::forceScheme('https');

        // Fallback untuk runtime yang mengizinkan perubahan konfigurasi PHP.
        // upload_max_filesize dan post_max_size biasanya PHP_INI_PERDIR,
        // sehingga tetap perlu diatur pada php.ini/server jika ini_set diabaikan.
        @ini_set('upload_max_filesize', '100M');
        @ini_set('post_max_size', '110M');
        @ini_set('memory_limit', '256M');

        Vite::prefetch(concurrency: 3);
    }
}
