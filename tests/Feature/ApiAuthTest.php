<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'secret123';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // إصدار الرموز الشخصية يحتاج مفاتيح Passport وعميلًا شخصيًا
        // مفاتيح معزولة في مجلد مؤقت حتى لا نلمس مفاتيح المشروع الحقيقية (storage/oauth-*.key)
        $keyDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ep-passport-test-keys';
        if (! is_dir($keyDir)) {
            @mkdir($keyDir, 0700, true);
        }
        Passport::loadKeysFrom($keyDir);

        try {
            Artisan::call('passport:keys', ['--force' => true]);
            Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test Personal', '--no-interaction' => true]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Passport personal client could not be created: '.$e->getMessage());
        }
    }

    public function test_register_returns_a_token_and_a_student_account(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sara', 'email' => 'Sara@Example.com',
            'password' => self::PW, 'password_confirmation' => self::PW, 'device_name' => 'pixel',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonPath('data.user.roles.0', 'student')
            ->assertJsonPath('data.token.token_type', 'Bearer')
            ->assertJsonPath('data.token.scopes', ['student']);
        $this->assertNotEmpty($res->json('data.token.access_token'));
        $this->assertDatabaseHas('users', ['email' => 'sara@example.com', 'status' => 'active']);
    }

    public function test_register_cannot_set_status_or_roles(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Evil', 'email' => 'evil@example.com', 'password' => self::PW, 'password_confirmation' => self::PW,
            'status' => 'suspended', 'roles' => ['super-admin'], 'scopes' => ['admin'],
        ])->assertCreated()->assertJsonPath('data.token.scopes', ['student']);

        $user = User::where('email', 'evil@example.com')->first();
        $this->assertSame('active', $user->status);
        $this->assertSame(['student'], $user->getRoleNames()->all());
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'taken@example.com', 'password' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_then_me_then_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => self::PW]);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'A@example.com', 'password' => self::PW])
            ->assertOk()->assertJsonPath('data.user.id', $user->id)->json('data.token.access_token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'a@example.com');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // تفريغ الحارس المخزّن بين الطلبات ثم التحقق من إبطال الرمز
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_rejects_wrong_credentials_and_inactive_accounts_with_the_same_message(): void
    {
        User::factory()->create(['email' => 'ok@example.com', 'password' => self::PW]);
        User::factory()->create(['email' => 'off@example.com', 'password' => self::PW, 'status' => 'suspended']);

        $bad  = $this->postJson('/api/v1/auth/login', ['email' => 'ok@example.com', 'password' => 'wrong-pass1'])->assertUnprocessable();
        $none = $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever1'])->assertUnprocessable();
        $off  = $this->postJson('/api/v1/auth/login', ['email' => 'off@example.com', 'password' => self::PW])->assertUnprocessable();

        $this->assertSame($bad->json('errors.email'), $none->json('errors.email'));
        $this->assertSame($bad->json('errors.email'), $off->json('errors.email'));
    }

    public function test_staff_tokens_carry_the_admin_scope(): void
    {
        $staff = User::factory()->create(['email' => 's@example.com', 'password' => self::PW]);
        $staff->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('orders.view', 'web'));

        $this->postJson('/api/v1/auth/login', ['email' => 's@example.com', 'password' => self::PW])
            ->assertOk()->assertJsonPath('data.token.scopes', ['student', 'admin']);
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->putJson('/api/v1/me', ['name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_suspended_account_is_blocked_even_with_a_valid_token(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        Passport::actingAs($user, ['student'], 'api');

        $this->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_profile_update_changes_only_the_name(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);
        Passport::actingAs($user, ['student'], 'api');

        $this->putJson('/api/v1/me', ['name' => 'New Name', 'email' => 'hack@example.com', 'status' => 'suspended'])
            ->assertOk()->assertJsonPath('data.name', 'New Name');

        $user->refresh();
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame('active', $user->status);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => self::PW]);
        Passport::actingAs($user, ['student'], 'api');

        $this->putJson('/api/v1/me/password', ['current_password' => 'nope-nope1', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/me/password', ['current_password' => self::PW, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_api_locale_follows_accept_language(): void
    {
        $en = $this->withHeader('Accept-Language', 'en')->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'whatever1']);
        $ar = $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'whatever1']);

        $this->assertNotSame($en->json('errors.email.0'), $ar->json('errors.email.0'));
        $this->assertSame('ar', $ar->headers->get('Content-Language'));
    }
}
