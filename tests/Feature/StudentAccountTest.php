<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** الملف الشخصي وتغيير كلمة المرور واستعادتها */
class StudentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function user(array $o = []): User
    {
        return User::factory()->create(array_merge(['password' => 'OldPass123', 'status' => 'active'], $o));
    }

    /* ---------- نسيت كلمة المرور ---------- */

    public function test_forgot_pages_render_and_login_links_to_it(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee(__('student.pw.send_link'));
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
    }

    public function test_active_user_gets_a_reset_link_and_response_is_generic(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertRedirect()->assertSessionHas('status', __('student.pw.link_sent'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_inactive_and_deleted_accounts_get_the_same_answer_but_no_mail(): void
    {
        Notification::fake();
        $inactive = $this->user(['status' => 'inactive']);
        $deleted = $this->user();
        $deleted->delete();

        foreach (['nobody@example.com', $inactive->email, $deleted->email] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertSessionHas('status', __('student.pw.link_sent'));
        }

        Notification::assertNothingSent();
    }

    public function test_reset_with_a_valid_token_changes_the_password(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertRedirect(route('login'))->assertSessionHas('status', __('student.pw.reset_done'));

        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));

        // الرابط يعمل مرة واحدة فقط
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'Another789', 'password_confirmation' => 'Another789',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }

    public function test_reset_rejects_bad_tokens_weak_passwords_and_inactive_accounts(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->post(route('password.update'), ['token' => 'x' . $token, 'email' => $user->email, 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrors('email');
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'NewPass456', 'password_confirmation' => 'Mismatch1'])
            ->assertSessionHasErrors('password');

        $inactive = $this->user(['status' => 'inactive']);
        $t2 = Password::createToken($inactive);
        $this->post(route('password.update'), ['token' => $t2, 'email' => $inactive->email, 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));
        $this->assertTrue(Hash::check('OldPass123', $inactive->fresh()->password));
    }

    public function test_reset_pages_are_for_guests_only(): void
    {
        $this->actingAs($this->user())->get(route('password.request'))->assertRedirect();
    }

    /* ---------- الملف الشخصي ---------- */

    public function test_profile_needs_login(): void
    {
        $this->get(route('student.profile.edit'))->assertRedirect(route('login'));
        $this->put(route('student.profile.update'), ['name' => 'X'])->assertRedirect(route('login'));
        $this->put(route('student.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_student_updates_only_his_name(): void
    {
        $user = $this->user(['name' => 'Old Name', 'email' => 'keep@example.com']);

        $this->actingAs($user)->get(route('student.profile.edit'))->assertOk()->assertSee('Old Name');

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => '  Sara Ali ', 'email' => 'hacked@example.com', 'status' => 'suspended', 'role' => 'super-admin',
        ])->assertRedirect()->assertSessionHas('status', __('student.profile.saved'));

        $user->refresh();
        $this->assertSame('Sara Ali', $user->name);
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame('active', $user->status);
        $this->assertFalse($user->hasRole('super-admin'));

        $this->put(route('student.profile.update'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_password_change_requires_the_current_password_and_a_strong_new_one(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->put(route('student.password.update'), ['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('student.password.update'), ['current_password' => 'OldPass123', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password');
        $this->put(route('student.password.update'), ['current_password' => 'OldPass123', 'password' => 'OldPass123', 'password_confirmation' => 'OldPass123'])
            ->assertSessionHasErrors('password');
        $this->put(route('student.password.update'), ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'Different1'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));
    }

    public function test_password_change_succeeds_and_rotates_the_remember_token(): void
    {
        $user = $this->user(['remember_token' => 'old-token-value']);

        $this->actingAs($user)->put(route('student.password.update'), [
            'current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertRedirect()->assertSessionHas('status', __('student.profile.password_changed'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass456', $user->password));
        $this->assertNotSame('old-token-value', $user->remember_token);
    }
}
