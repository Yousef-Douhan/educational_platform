<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        Schema::defaultStringLength(191);
        Passport::loadKeysFrom(storage_path());
        Model::preventLazyLoading(! app()->isProduction());
        // معاملات المسارات أرقام فقط حتى لا يلتقط /{role} الكلمتين create وtrashed
        Route::pattern('user', '[0-9]+');
        Route::pattern('user_profile', '[0-9]+');
        Route::pattern('role', '[0-9]+');
        Route::pattern('permission', '[0-9]+');
        Route::pattern('category', '[0-9]+');
        Route::pattern('course', '[0-9]+');
        Route::pattern('section', '[0-9]+');
        Route::pattern('lesson', '[0-9]+');
        Route::pattern('attachment', '[0-9]+');
        Route::pattern('enrollment', '[0-9]+');
        Route::pattern('id', '[0-9]+');
        Route::pattern('quiz', '[0-9]+');
        Route::pattern('question', '[0-9]+');
        Route::pattern('certificate', '[0-9]+');
        Route::pattern('certificate_template', '[0-9]+');
        Route::pattern('coupon', '[0-9]+');
        Route::pattern('cart', '[0-9]+');
        Route::pattern('order', '[0-9]+');
        Route::pattern('payment', '[0-9]+');
        Route::pattern('attempt', '[0-9]+');

        RateLimiter::for('certificate-verify', fn(Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
