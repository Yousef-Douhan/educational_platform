<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // الحسابات غير النشطة أو الموقوفة لا تدخل
        $attempt = Auth::attempt(
            [...$credentials, 'status' => 'active'],
            $request->boolean('remember')
        );

        if (! $attempt) {
            throw ValidationException::withMessages([
                'email' => __('student.auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // الموظف إلى لوحة التحكم، والطالب إلى دوراته
        $home = $request->user()->isStaff() ? route('cms.admin.dashboard') : route('student.courses.mine');

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
