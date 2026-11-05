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
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
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
    $middleware->api(prepend: [\App\Http\Middleware\ApiLocale::class]);
    $middleware->api(append: [\App\Http\Middleware\ApiSecurityHeaders::class]);

    $middleware->alias([
        'staff'              => \App\Http\Middleware\EnsureStaff::class,
        'active'             => \App\Http\Middleware\EnsureActiveAccount::class,
        'token.scope'        => \App\Http\Middleware\EnsureTokenScope::class,
        'role'               => RoleMiddleware::class,
        'permission'         => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 404 موحّدة في الـAPI: لا تُسرَّب أسماء النماذج في رسالة الاستثناء
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('api.not_found'), 'code' => 'not_found'], 404);
            }
        });
    })->create();
