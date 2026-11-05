<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات أمان لردود الـAPI: لا تخزين لردود JSON (بيانات شخصية/مالية)، ومنع تخمين نوع المحتوى.
 * ردود الملفات (صور الإيصالات، PDF) تضبط ترويساتها بنفسها فلا تُمَس.
 */
class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        if ($response instanceof JsonResponse) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
