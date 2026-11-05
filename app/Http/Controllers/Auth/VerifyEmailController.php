<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** تحقق البريد: الرابط موقّع ومرتبط بحساب الدخول الحالي (id وhash يطابقان المستخدم) */
class VerifyEmailController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('student.courses.mine')
            : view('student.auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('student.courses.mine')->with('status', __('student.verify.done'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('student.courses.mine');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('student.verify.sent'));
    }
}
