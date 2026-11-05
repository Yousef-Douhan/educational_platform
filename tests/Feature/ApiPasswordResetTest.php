<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'])->assertOk();
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($a->json('message'), $b->json('message'));
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_inactive_accounts_get_no_reset_link(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'off@example.com', 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'off@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reset_changes_the_password_and_the_token_is_single_use(): void
    {
        $user  = User::factory()->create(['email' => 'r@example.com', 'password' => 'oldpass123']);
        $token = Password::createToken($user);
        $body  = ['token' => $token, 'email' => 'r@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'];

        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));

        $this->postJson('/api/v1/auth/reset-password', $body)->assertUnprocessable()->assertJsonPath('code', 'invalid_reset');
    }

    public function test_reset_rejects_a_wrong_token_and_weak_passwords(): void
    {
        $user = User::factory()->create(['email' => 'w@example.com', 'password' => 'oldpass123']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'bogus', 'email' => 'w@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertUnprocessable()->assertJsonPath('code', 'invalid_reset');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::createToken($user), 'email' => 'w@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    public function test_verification_email_can_be_resent_only_when_unverified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Passport::actingAs($user, ['student'], 'api');

        $this->postJson('/api/v1/auth/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);

        $this->getJson('/api/v1/me')->assertOk(); // ما زال الحساب يعمل

        $verified = User::factory()->create();
        Passport::actingAs($verified, ['student'], 'api');
        $this->postJson('/api/v1/auth/email/verification-notification')->assertOk();
        Notification::assertNotSentTo($verified, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_reset_requires_a_valid_email_shape(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/forgot-password', [])->assertUnprocessable();
    }
}
