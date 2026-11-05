<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Throwable;

trait RespondsWithErrors
{
    /** خطأ منطقي من طبقة الخدمات: الرسالة مترجمة جاهزة، والرمز (reason) ثابت للتطبيق */
    protected function fail(Throwable $e, int $status = 422): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code'    => property_exists($e, 'reason') ? $e->reason : null,
        ], $status);
    }

    protected function failMessage(string $message, string $code, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
