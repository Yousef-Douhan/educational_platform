<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** تحقق البريد: الرابط موقّع، والشراء والتسجيل يشترطان بريدًا محققًا */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function unverified(): User
    {
        return User::factory()->unverified()->create();
    }

    private function link(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_register_sends_a_verification_email_and_shows_the_banner(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('student.courses.mine'));

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('student.courses.mine'))->assertOk()->assertSee(__('student.verify.banner'));
    }

    public function test_unverified_users_cannot_enroll_checkout_pay_or_submit_receipts(): void
    {
        $user = $this->unverified();
        $free = Course::factory()->published()->create();
        app(CartService::class)->add($user, Course::factory()->published()->paid(50)->create());

        $this->actingAs($user);
        $this->post(route('student.courses.enroll', $free->slug))->assertRedirect(route('verification.notice'));
        $this->post(route('student.checkout'))->assertRedirect(route('verification.notice'));
        $this->post(route('student.orders.pay', 'ORD-AAAAAAAA'))->assertRedirect(route('verification.notice'));
        $this->post(route('student.orders.receipt', 'ORD-AAAAAAAA'))->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('enrollments', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_verified_users_can_enroll(): void
    {
        $user = User::factory()->create();
        $free = Course::factory()->published()->create();

        $this->actingAs($user)->post(route('student.courses.enroll', $free->slug))->assertRedirect(route('student.courses.mine'));
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $free->id]);
    }

    public function test_browsing_and_the_notice_page_stay_open_to_unverified_users(): void
    {
        $user = $this->unverified();

        $this->actingAs($user)->get(route('student.courses.index'))->assertOk();
        $this->get(route('student.courses.mine'))->assertOk();
        $this->get(route('verification.notice'))->assertOk()->assertSee($user->email);
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = $this->unverified();

        $this->actingAs($user)->get($this->link($user))->assertRedirect(route('student.courses.mine'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_expired_and_foreign_links_are_refused(): void
    {
        $user = $this->unverified();
        $other = $this->unverified();
        $this->actingAs($user);

        $this->get($this->link($user, str_repeat('a', 40)))->assertForbidden();
        $this->get($this->link($other))->assertForbidden();
        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden(); // بلا توقيع

        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($expired)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_verify_link_needs_login(): void
    {
        $user = $this->unverified();

        $this->get($this->link($user))->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_sends_a_new_email_only_when_unverified(): void
    {
        Notification::fake();
        $user = $this->unverified();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect()->assertSessionHas('status', __('student.verify.sent'));
        Notification::assertSentTo($user, VerifyEmail::class);

        Notification::fake();
        $verified = User::factory()->create();
        $this->actingAs($verified)->post(route('verification.send'))->assertRedirect(route('student.courses.mine'));
        Notification::assertNothingSent();
    }

    public function test_notice_redirects_verified_users(): void
    {
        $this->actingAs(User::factory()->create())->get(route('verification.notice'))->assertRedirect(route('student.courses.mine'));
    }
}
