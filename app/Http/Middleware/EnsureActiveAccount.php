<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الحساب الذي أُوقف أو عُطّل بعد إصدار الرمز لا يستمر بالدخول:
 * تُلغى رموزه ويُرفض الطلب فورًا.
 */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $user->tokens()->get()->each(fn ($token) => $token->revoke());

            return response()->json(['message' => __('api.inactive')], 403);
        }

        return $next($request);
    }
}
