<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** حساب الطالب: الاسم وكلمة المرور فقط. البريد لا يتغير من هنا (يحتاج تحققًا) والحالة والأدوار ليست من الطلب */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('student.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);

        $request->user()->forceFill(['name' => trim($data['name'])])->save();

        return back()->with('status', __('student.profile.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        // remember_token جديد يُبطل كوكيات "تذكرني" القديمة على الأجهزة الأخرى
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();

        return back()->with('status', __('student.profile.password_changed'));
    }
}
