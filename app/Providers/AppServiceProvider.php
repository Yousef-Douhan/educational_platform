<?php

namespace App\Providers;

use App\Services\AuditLogger;
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
        // رموز الـAPI الشخصية: صلاحياتها محددة وعمرها محدود
        Passport::tokensCan([
            'student' => 'Student access',
            'admin'   => 'CMS administration access',
        ]);
        Passport::personalAccessTokensExpireIn(now()->addDays(30));
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
        Route::pattern('receipt', '[0-9]+');
        Route::pattern('attempt', '[0-9]+');
        Route::pattern('progress', '[0-9]+');
        Route::pattern('live_session', '[0-9]+');
        Route::pattern('audit_log', '[0-9]+');
        // معرّف الإشعار UUID
        Route::pattern('notification', '[0-9a-fA-F\-]{36}');

        // سجل التدقيق: مراقبة النماذج الحساسة وأحداث الدخول
        $this->app->make(AuditLogger::class)->register();

        // البوابات تتشارك عناوين قليلة، فالحد سخي لكنه موجود
        RateLimiter::for('payment-webhooks', fn(Request $request) => Limit::perMinute(300)->by($request->ip()));
        // الـAPI: حد عام لكل مستخدم/عنوان، وحد أضيق لمحاولات الدخول والتسجيل (ضد التخمين)
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user('api')?->id ?: $request->ip()));
        // حدّان: لكل (IP + بريد) ولكل IP وحده، فتدوير البريد من عنوان واحد لا يتجاوز الحد
        RateLimiter::for('api-auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip().'|'.mb_strtolower(mb_substr((string) $request->input('email'), 0, 255))),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        // الإدارة: القراءة أوسع من الكتابة، والمفتاح هو المستخدم
        RateLimiter::for('api-admin', fn (Request $request) => $request->isMethodSafe()
            ? Limit::perMinute(240)->by('adm-r:'.($request->user('api')?->id ?: $request->ip()))
            : Limit::perMinute(60)->by('adm-w:'.($request->user('api')?->id ?: $request->ip())));
        RateLimiter::for('certificate-verify', fn(Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
