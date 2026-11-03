<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // إشعارات البوابات: بلا مجموعة web (لا جلسة ولا CSRF)، والمصادقة بالتوقيع
            \Illuminate\Support\Facades\Route::prefix('webhooks')
                ->middleware('throttle:payment-webhooks')
                ->group(base_path('routes/webhooks.php'));

            // واجهة الطالب (جلسة + CSRF)
            \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/student.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);

    $middleware->alias([
        'staff'              => \App\Http\Middleware\EnsureStaff::class,
        'role'               => RoleMiddleware::class,
        'permission'         => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
