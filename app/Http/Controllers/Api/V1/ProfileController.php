<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** حساب المستخدم الحالي: الاسم وكلمة المرور فقط (البريد والحالة والأدوار ليست من الطلب). */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);

        $request->user()->forceFill(['name' => trim($data['name'])])->save();

        return response()->json(['message' => __('api.profile_updated'), 'data' => new UserResource($request->user()->refresh())]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:api'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user    = $request->user();
        $current = $user->token();

        $user->forceFill(['password' => $data['password']])->save();

        // تسجيل الخروج من كل الأجهزة الأخرى (يبقى رمز هذا الجهاز)
        $user->tokens()
            ->when($current?->id, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get()
            ->each(fn ($token) => $token->revoke());

        return response()->json(['message' => __('api.password_changed')]);
    }
}
