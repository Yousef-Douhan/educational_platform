<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** تحصين الـAPI: حدود الدخول، سقف الرموز، تسجيل الخروج الشامل، ترويسات الأمان، إلغاء الرموز عند التغييرات الحساسة */
class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'secret123';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

    private function login(string $email, string $device = 'dev'): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PW, 'device_name' => $device])
            ->assertOk()->json('data.token.access_token');
    }

    public function test_json_responses_carry_security_headers(): void
    {
        $res = $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'whatever1']);

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_login_is_limited_per_ip_even_when_rotating_emails(): void
    {
        $last = null;
        for ($i = 1; $i <= 31; $i++) {
            $last = $this->postJson('/api/v1/auth/login', ['email' => "user{$i}@example.com", 'password' => 'whatever1']);
        }

        $last->assertStatus(429);
    }

    public function test_active_tokens_are_capped_per_user(): void
    {
        $user = User::factory()->create(['email' => 'cap@example.com', 'password' => self::PW]);
        for ($i = 0; $i < 12; $i++) {
            $user->createToken("old-{$i}", ['student']);
        }

        $this->login('cap@example.com');

        $this->assertSame(10, $user->tokens()->where('revoked', false)->count());
    }

    public function test_logout_all_revokes_every_token(): void
    {
        User::factory()->create(['email' => 'all@example.com', 'password' => self::PW]);
        $a = $this->login('all@example.com', 'phone');
        $b = $this->login('all@example.com', 'tablet');

        $this->withToken($a)->postJson('/api/v1/auth/logout-all')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($b)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($a)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_admin_changing_password_status_or_roles_revokes_the_users_tokens(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('super-admin', 'web'));

        foreach ([['password' => 'brandnew123'], ['status' => 'inactive']] as $change) {
            $victim = User::factory()->create(['password' => self::PW, 'status' => 'active']);
            $token  = $this->login($victim->email);

            Passport::actingAs($admin, ['student', 'admin'], 'api');
            $this->putJson("/api/v1/admin/users/{$victim->id}", $change + [
                'name' => $victim->name, 'email' => $victim->email, 'status' => 'active',
            ] + [])->assertOk();

            $this->assertSame(0, $victim->tokens()->where('revoked', false)->count(), json_encode($change));
        }
    }

    public function test_admin_editing_own_account_keeps_own_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['password' => self::PW]);
        $admin->assignRole(Role::findByName('super-admin', 'web'));
        $token = $this->login($admin->email);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson("/api/v1/admin/users/{$admin->id}", [
            'name' => 'Renamed', 'email' => $admin->email, 'status' => 'active', 'password' => 'brandnew123',
        ]);

        $this->assertSame(1, $admin->tokens()->where('revoked', false)->count());
    }

    public function test_deleting_a_user_revokes_their_tokens(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('super-admin', 'web'));
        $victim = User::factory()->create(['password' => self::PW]);
        $this->login($victim->email);

        Passport::actingAs($admin, ['student', 'admin'], 'api');
        $this->deleteJson("/api/v1/admin/users/{$victim->id}")->assertOk();

        $this->assertSame(0, $victim->tokens()->where('revoked', false)->count());
    }
}
