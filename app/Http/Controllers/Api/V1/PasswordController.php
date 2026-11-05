<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * استعادة كلمة المرور. الرد واحد دائمًا سواء وُجد البريد أم لا (لا كشف للحسابات)،
 * والحسابات غير النشطة لا تستلم رابطًا ولا تُعاد كلمة مرورها. الإعادة تلغي كل الرموز.
 */
class PasswordController extends Controller
{
    use RespondsWithErrors;

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'status' => 'active']);

        return response()->json(['message' => __('student.pw.link_sent')]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower(trim($data['email'])), 'status' => 'active', 'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->get()->each(fn ($token) => $token->revoke());
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->failMessage(__('student.pw.invalid'), 'invalid_reset');
        }

        return response()->json(['message' => __('student.pw.reset_done')]);
    }

    /** إعادة إرسال رسالة تحقق البريد للمستخدم الحالي */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => __('student.verify.sent')]);
    }
}
