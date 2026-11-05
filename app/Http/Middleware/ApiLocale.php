<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغة الـAPI من ترويسة Accept-Language (ar أو en)، وإلا اللغة الافتراضية.
 * ويفرض الرد بصيغة JSON حتى لو نسي العميل ترويسة Accept (متحكمات الـCMS تعتمد expectsJson).
 */
class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $locale = $request->getPreferredLanguage(['ar', 'en']);

        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
