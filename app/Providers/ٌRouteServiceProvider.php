<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Schema;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Schema; // تأكد من إضافة هذا السطر في الأعلى

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
        Route::pattern('section', '[0-9]+');
        Route::pattern('lesson', '[0-9]+');
        Route::pattern('attachment', '[0-9]+');
        Route::pattern('id', '[0-9]+');
        Route::pattern('enrollment', '[0-9]+');
    }
}
