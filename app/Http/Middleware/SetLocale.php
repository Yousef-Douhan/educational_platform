<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يطبّق لغة اختارها المستخدم (كوكي مشفّر) من قائمة بيضاء فقط.
 * بلا اختيار سابق لا يمس لغة التطبيق الافتراضية.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];
    public const COOKIE    = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $choice = $request->cookie(self::COOKIE);

        if (is_string($choice) && in_array($choice, self::SUPPORTED, true)) {
            app()->setLocale($choice);
        }

        return $next($request);
    }
}
