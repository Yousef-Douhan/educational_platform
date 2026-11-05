<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * تسجيل الدخول/إنشاء الحساب بـGoogle (Authorization Code + PKCE + state)، دون حزم إضافية.
 *
 * سياسة الربط:
 *  - لا يُقبل إلا بريد موثَّق من Google (email_verified).
 *  - حساب موجود بنفس البريد يُربط تلقائيًا للطلاب فقط؛ حسابات الموظفين لا تُربط بالبريد (تدخل بكلمة المرور).
 *  - إن كان الحساب المحلي غير موثَّق البريد تُبطل كلمة مروره القديمة ورموزه، منعًا لاستباق التسجيل بالبريد.
 *  - الحسابات الموقوفة/المحذوفة لا تدخل.
 */
class GoogleAuthController extends Controller
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const INFO_URL  = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->fail('google_unavailable');
        }

        $state    = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(['google_oauth_state' => $state, 'google_oauth_verifier' => $verifier]);

        return redirect()->away(self::AUTH_URL.'?'.http_build_query([
            'client_id'             => config('services.google.client_id'),
            'redirect_uri'          => $this->redirectUri(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->fail('google_unavailable');
        }

        // الحالة والمُتحقِّق صالحان لمحاولة واحدة فقط
        $state    = $request->session()->pull('google_oauth_state');
        $verifier = $request->session()->pull('google_oauth_verifier');
        $given    = $request->query('state');
        $code     = $request->query('code');

        if ($request->query('error') !== null
            || ! is_string($state) || ! is_string($given) || ! hash_equals($state, $given)
            || ! is_string($verifier) || ! is_string($code) || $code === '' || strlen($code) > 2048) {
            Log::warning('google_oauth: callback rejected', [
                'google_error' => is_string($request->query('error')) ? $request->query('error') : null,
                'has_state'    => is_string($state),
                'has_verifier' => is_string($verifier),
                'state_match'  => is_string($state) && is_string($given) && hash_equals($state, $given),
                'has_code'     => is_string($code) && $code !== '',
            ]);

            return $this->fail('google_failed');
        }

        $profile = $this->fetchProfile($code, $verifier);

        if ($profile === null) {
            return $this->fail('google_failed');
        }

        if (! $profile['verified']) {
            return $this->fail('google_unverified');
        }

        try {
            [$user, $error] = DB::transaction(fn () => $this->resolveUser($profile));
        } catch (UniqueConstraintViolationException) {
            return $this->fail('google_failed');
        }

        if ($error !== null) {
            return $this->fail($error);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $home = $user->isStaff() ? route('cms.admin.dashboard') : route('student.courses.mine');

        return redirect()->intended($home);
    }

    /** @return array{sub:string,email:string,name:string,verified:bool}|null */
    private function fetchProfile(string $code, string $verifier): ?array
    {
        try {
            $token = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri'  => $this->redirectUri(),
                'grant_type'    => 'authorization_code',
                'code_verifier' => $verifier,
            ]);

            $access = $token->successful() ? $token->json('access_token') : null;

            if (! is_string($access) || $access === '') {
                Log::warning('google_oauth: token exchange failed', [
                    'status'            => $token->status(),
                    'error'             => $token->json('error'),
                    'error_description' => $token->json('error_description'),
                    'redirect_uri'      => $this->redirectUri(),
                ]);

                return null;
            }

            $info = Http::withToken($access)->timeout(10)->get(self::INFO_URL);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $sub   = $info->json('sub');
        $email = $info->json('email');

        if (! $info->successful() || ! is_string($sub) || $sub === '' || strlen($sub) > 64
            || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            return null;
        }

        $email = mb_strtolower(trim($email));
        $name  = $info->json('name');
        $name  = is_string($name) && trim($name) !== '' ? trim($name) : Str::before($email, '@');

        return [
            'sub'      => $sub,
            'email'    => $email,
            'name'     => mb_substr($name, 0, 255),
            'verified' => filter_var($info->json('email_verified'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @return array{0:?User,1:?string} المستخدم أو مفتاح رسالة الخطأ */
    private function resolveUser(array $p): array
    {
        $user = User::where('google_id', $p['sub'])->first();

        if ($user) {
            return $user->status === 'active' ? [$user, null] : [null, 'google_blocked'];
        }

        $existing = User::withTrashed()->where('email', $p['email'])->first();

        if ($existing) {
            if ($existing->trashed() || $existing->status !== 'active') {
                return [null, 'google_blocked'];
            }

            if ($existing->isStaff()) {
                return [null, 'google_staff'];
            }

            if ($existing->google_id !== null) {
                return [null, 'google_failed'];
            }

            $attrs = ['google_id' => $p['sub']];

            if ($existing->email_verified_at === null) {
                // قد يكون الحساب مسجَّلًا مسبقًا من شخص آخر بهذا البريد: تُبطل كلمة مروره وجلسات "تذكرني" ورموز الـAPI
                $attrs += ['password' => Str::random(40), 'remember_token' => Str::random(60), 'email_verified_at' => now()];
                $existing->tokens()->get()->each(fn ($t) => $t->revoke());
            }

            $existing->forceFill($attrs)->save();

            return [$existing, null];
        }

        $user = new User();
        $user->forceFill([
            'name'              => $p['name'],
            'email'             => $p['email'],
            'google_id'         => $p['sub'],
            'password'          => Str::random(40), // لا كلمة مرور معروفة؛ يمكن ضبطها عبر "نسيت كلمة المرور"
            'status'            => 'active',
            'email_verified_at' => now(),
        ])->save();
        $user->assignRole(Role::findOrCreate('student', 'web'));

        return [$user, null];
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function redirectUri(): string
    {
        return config('services.google.redirect') ?: route('auth.google.callback');
    }

    private function fail(string $key): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => __("student.auth.{$key}")]);
    }
}
