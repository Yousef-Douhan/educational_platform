<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يشترط أن يحمل رمز Passport الصلاحية المطلوبة (student | admin).
 * الاستخدام: ->middleware('token.scope:student')
 */
class EnsureTokenScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'tokenCan') || ! $user->tokenCan($scope)) {
            return response()->json(['message' => __('api.forbidden_scope'), 'code' => 'insufficient_scope'], 403);
        }

        return $next($request);
    }
}
