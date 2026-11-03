<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('student.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            // unique يشمل الحسابات المحذوفة حذفًا ناعمًا، فلا يُعاد استخدام بريد قديم
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // الحقول مختارة بالاسم: الحالة والصلاحيات لا تأتي من الطلب أبدًا
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => trim($data['name']),
                'email'    => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);
            $user->assignRole(Role::findOrCreate('student', 'web'));

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.courses.mine')->with('status', __('student.messages.welcome'));
    }
}
