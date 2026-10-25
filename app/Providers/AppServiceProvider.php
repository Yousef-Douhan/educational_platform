<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Schema;

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
        Schema::defaultStringLength(191);
        // استخدام المسار الصحيح للمجلد أو التعليق عليه لاستخدام الافتراضي
        Passport::loadKeysFrom(storage_path());
       
    }
}
