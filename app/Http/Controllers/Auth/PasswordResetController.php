<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * استعادة كلمة المرور. الرد على الطلب واحد دائمًا سواء وُجد البريد أم لا (لا كشف للحسابات)،
 * والحسابات غير النشطة لا تستلم رابطًا ولا تُعاد كلمة مرورها.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('student.auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'status' => 'active']);

        return back()->with('status', __('student.pw.link_sent'));
    }

    public function form(Request $request, string $token): View
    {
        return view('student.auth.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
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
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('student.pw.invalid')]);
        }

        return redirect()->route('login')->with('status', __('student.pw.reset_done'));
    }
}
