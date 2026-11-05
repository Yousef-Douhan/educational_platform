<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** تسجيل الدخول/إنشاء الحساب بـGoogle: الحالة وPKCE وسياسة الربط (Http مُحاكى بالكامل، لا اتصال حقيقي) */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'http://localhost/auth/google/callback']]);
    }

    /** يبدأ التدفق ويرجع قيمة state التي أرسلها التطبيق إلى Google */
    private function start(): string
    {
        $location = $this->get('/auth/google')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

        return $q['state'];
    }

    private function finish(array $info, ?string $state = null, string $query = 'code=abc')
    {
        Http::fake([
            'oauth2.googleapis.com/*'         => Http::response(['access_token' => 'tok']),
            'openidconnect.googleapis.com/*'  => Http::response($info),
        ]);
        $state ??= $this->start();

        return $this->get("/auth/google/callback?{$query}&state={$state}");
    }

    private function info(array $over = []): array
    {
        return $over + ['sub' => 'g-123', 'email' => 'Sara@Example.com', 'email_verified' => true, 'name' => 'Sara'];
    }

    public function test_redirect_carries_state_pkce_and_scopes(): void
    {
        $location = $this->get('/auth/google')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $location);
        $this->assertSame('cid', $q['client_id']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertSame(40, strlen($q['state']));
        $this->assertStringContainsString('email', $q['scope']);
    }

    public function test_unconfigured_google_is_hidden_and_blocked(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertDontSee('/auth/google');
        $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_buttons_show_on_login_and_register_when_configured(): void
    {
        $this->get('/login')->assertOk()->assertSee('/auth/google');
        $this->get('/register')->assertOk()->assertSee('/auth/google');
    }

    public function test_callback_rejects_wrong_or_missing_state_and_reused_state(): void
    {
        $this->start();
        $this->get('/auth/google/callback?code=abc&state=forged')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();

        // الحالة تُستهلك: لا إعادة استخدام
        $state = $this->start();
        $this->finish($this->info(), $state)->assertRedirect();
        $this->post('/logout');
        $this->get("/auth/google/callback?code=abc&state={$state}")->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_callback_rejects_google_error_param(): void
    {
        $state = $this->start();

        $this->get("/auth/google/callback?error=access_denied&state={$state}")->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_new_google_user_is_created_as_verified_active_student(): void
    {
        $this->finish($this->info())->assertRedirect(route('student.courses.mine'));

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_google_user_logs_in_even_if_email_changed_at_google(): void
    {
        $this->finish($this->info())->assertRedirect();
        $this->post('/logout');

        $this->finish($this->info(['email' => 'new-address@example.com']))->assertRedirect();

        $this->assertSame(1, User::count());
        $this->assertAuthenticated();
    }

    public function test_existing_verified_student_is_linked_not_duplicated(): void
    {
        $u = User::factory()->create(['email' => 'sara@example.com']);

        $this->finish($this->info())->assertRedirect();

        $this->assertSame(1, User::count());
        $this->assertSame('g-123', $u->fresh()->google_id);
        $this->assertAuthenticatedAs($u);
    }

    public function test_linking_an_unverified_local_account_kills_its_old_password(): void
    {
        $u = User::factory()->unverified()->create(['email' => 'sara@example.com', 'password' => 'attacker-pass1']);

        $this->finish($this->info())->assertRedirect();

        $u->refresh();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('attacker-pass1', $u->password));
        $this->assertNotNull($u->email_verified_at);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->finish($this->info(['email_verified' => false]))->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_suspended_and_deleted_accounts_cannot_sign_in(): void
    {
        $u = User::factory()->create(['email' => 'sara@example.com', 'status' => 'suspended']);
        $this->finish($this->info())->assertRedirect(route('login'));
        $this->assertGuest();

        $u->update(['status' => 'active']);
        $u->delete();
        $this->finish($this->info())->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_staff_accounts_are_not_auto_linked_by_email(): void
    {
        $staff = User::factory()->create(['email' => 'sara@example.com']);
        $staff->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));

        $this->finish($this->info())->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($staff->fresh()->google_id);
    }

    public function test_failed_token_exchange_is_handled(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);
        $state = $this->start();

        $this->get("/auth/google/callback?code=bad&state={$state}")->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_signed_in_users_are_bounced_from_the_flow(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/auth/google')->assertRedirect();
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect();
    }
}
