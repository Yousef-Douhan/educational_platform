<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** مصادقة الـAPI برموز Passport الشخصية. الدخول والتسجيل بحد طلبات أضيق (throttle:api-auth). */
class AuthController extends Controller
{
    /** bcrypt صالح لمقارنة وهمية عند غياب المستخدم (يوحّد زمن الاستجابة) */
    private const MAX_TOKENS = 10;

    private const DUMMY_HASH = '$2y$12$XjCUzc8EFqgGQQyr7lduNe4LqMumHaTpKEnOsl.lTh6cgMRaS9Uxu';

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:255'],
            'email'       => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password'    => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // الحقول بالاسم: الحالة والأدوار لا تأتي من الطلب أبدًا
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => trim($data['name']),
                'email'    => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);
            $user->assignRole(Role::findOrCreate('student', 'web'));

            return $user;
        });

        try {
            event(new Registered($user)); // رسالة تحقق البريد؛ فشلها لا يُفشل التسجيل
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => __('api.registered'),
            'data'    => ['user' => new UserResource($user)] + $this->issueToken($user, $data['device_name'] ?? null),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();

        // فحص كلمة المرور يتم دائمًا (حتى لو لا مستخدم) كي لا يُكشف وجود البريد بالزمن
        $ok = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $ok || $user->status !== 'active') {
            throw ValidationException::withMessages(['email' => __('student.auth.failed')]);
        }

        return response()->json([
            'data' => ['user' => new UserResource($user)] + $this->issueToken($user, $data['device_name'] ?? null),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json(['message' => __('api.logged_out')]);
    }

    /** تسجيل الخروج من كل الأجهزة (إلغاء كل الرموز) */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->get()->each(fn ($token) => $token->revoke());

        return response()->json(['message' => __('api.logged_out')]);
    }

    /** الصلاحيات: كل حساب "student"، والموظف يضاف له "admin". لا تُقبل صلاحيات من الطلب. */
    private function issueToken(User $user, ?string $device): array
    {
        $scopes = $user->isStaff() ? ['student', 'admin'] : ['student'];
        $result = $user->createToken($device ?: 'api', $scopes);

        // سقف الرموز الفعّالة: يُلغى الأقدم عند تجاوزه فلا تتراكم الرموز بلا حد
        $user->tokens()->where('revoked', false)->orderByDesc('created_at')->orderByDesc('id')
            ->get()->slice(self::MAX_TOKENS)->each(fn ($token) => $token->revoke());

        return ['token' => [
            'access_token' => $result->accessToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $result->token->expires_at?->toIso8601String(),
            'scopes'       => $scopes,
        ]];
    }
}
