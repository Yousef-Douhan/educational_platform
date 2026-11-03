<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar'); // العناوين العربية هي المعروضة، فالاختبارات لا تتبع APP_LOCALE
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function student(): User
    {
        return User::factory()->create();
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('carts.view', 'web'));

        return $user;
    }

    private function free(array $o = []): Course
    {
        return Course::factory()->published()->create($o);
    }

    private function paid(array $o = []): Course
    {
        return Course::factory()->published()->paid(100)->create($o);
    }

    /* ---------- التسجيل ---------- */

    public function test_register_creates_a_student_and_logs_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Sara', 'email' => 'Sara@Example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('student.courses.mine'));

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->isStaff());
    }

    public function test_register_ignores_status_and_role_fields(): void
    {
        $this->post(route('register'), [
            'name' => 'Eve', 'email' => 'eve@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'status' => 'suspended', 'role' => 'super-admin', 'roles' => ['super-admin'], 'is_admin' => 1,
        ])->assertRedirect();

        $user = User::where('email', 'eve@example.com')->firstOrFail();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertNotSame('suspended', $user->status);
    }

    public function test_register_validation(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => '', 'email' => 'nope', 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->post(route('register'), [
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_existing_email_even_soft_deleted(): void
    {
        $old = User::factory()->create(['email' => 'old@example.com']);
        $old->delete();

        $this->post(route('register'), [
            'name' => 'New', 'email' => 'old@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('email');
    }

    public function test_register_page_is_for_guests_only(): void
    {
        $this->get(route('register'))->assertOk();
        $this->actingAs($this->student())->get(route('register'))->assertRedirect();
    }

    /* ---------- الدخول وفصل الواجهات ---------- */

    public function test_login_sends_student_to_his_courses_and_staff_to_the_cms(): void
    {
        $student = $this->student();
        $this->post(route('login'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student.courses.mine'));
        auth()->logout();

        $staff = $this->staff();
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('cms.admin.dashboard'));
    }

    public function test_student_cannot_open_the_cms_dashboard(): void
    {
        $this->actingAs($this->student())->get(route('cms.admin.dashboard'))
            ->assertRedirect(route('student.courses.mine'));

        $this->actingAs($this->student())->getJson(route('cms.admin.dashboard'))->assertForbidden();
    }

    public function test_staff_can_open_the_cms_dashboard(): void
    {
        $this->actingAs($this->staff())->get(route('cms.admin.dashboard'))->assertOk();
    }

    public function test_account_pages_need_login(): void
    {
        foreach (['student.courses.mine', 'student.cart.show', 'student.orders.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        $this->post(route('student.checkout'))->assertRedirect(route('login'));
    }

    /* ---------- الكتالوج ---------- */

    public function test_home_and_catalog_show_only_published_courses(): void
    {
        $this->free(['title_en' => 'Visible One', 'title_ar' => 'ظاهرة']);
        Course::factory()->create(['title_en' => 'Hidden Draft', 'title_ar' => 'مسودة مخفية']);
        Course::factory()->create(['status' => 'archived', 'title_ar' => 'مؤرشفة مخفية']);

        $this->get(route('student.home'))->assertOk()->assertSee('ظاهرة')->assertDontSee('مسودة مخفية');
        $this->get(route('student.courses.index'))->assertOk()
            ->assertSee('ظاهرة')->assertDontSee('مسودة مخفية')->assertDontSee('مؤرشفة مخفية');
    }

    public function test_catalog_filters(): void
    {
        $this->free(['title_ar' => 'مجانية-أ', 'level' => 'beginner']);
        $this->paid(['title_ar' => 'مدفوعة-ب', 'level' => 'advanced']);

        $this->get(route('student.courses.index', ['price' => 'free']))->assertSee('مجانية-أ')->assertDontSee('مدفوعة-ب');
        $this->get(route('student.courses.index', ['price' => 'paid']))->assertSee('مدفوعة-ب')->assertDontSee('مجانية-أ');
        $this->get(route('student.courses.index', ['level' => 'advanced']))->assertSee('مدفوعة-ب')->assertDontSee('مجانية-أ');
        $this->get(route('student.courses.index', ['q' => 'مجانية']))->assertSee('مجانية-أ')->assertDontSee('مدفوعة-ب');
    }

    public function test_catalog_ignores_garbage_filters(): void
    {
        $this->free();

        $this->get(route('student.courses.index', ['sort' => 'id; drop table users', 'level' => ['x'], 'price' => '1', 'q' => ['a'], 'category' => ['b']]))
            ->assertOk();
        $this->assertDatabaseCount('users', User::count());
    }

    public function test_catalog_sorts_by_effective_price(): void
    {
        $this->paid(['title_ar' => 'غالية-س', 'price' => 200]);
        $this->paid(['title_ar' => 'رخيصة-ص', 'price' => 300, 'discount_price' => 50]);

        $this->get(route('student.courses.index', ['sort' => 'price_asc']))
            ->assertSeeInOrder(['رخيصة-ص', 'غالية-س']);
        $this->get(route('student.courses.index', ['sort' => 'price_desc']))
            ->assertSeeInOrder(['غالية-س', 'رخيصة-ص']);
    }

    public function test_course_page_hides_unpublished_courses(): void
    {
        $draft = Course::factory()->create();
        $this->get(route('student.courses.show', $draft->slug))->assertNotFound();
        $this->get(route('student.courses.show', 'does-not-exist'))->assertNotFound();
    }

    public function test_course_page_never_leaks_lesson_content(): void
    {
        $course  = $this->free();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->video()->create([
            'section_id' => $section->id, 'is_published' => true, 'title_ar' => 'درس-ظاهر',
            'video_url' => 'https://www.youtube.com/watch?v=SECRET999',
        ]);
        Lesson::factory()->create([
            'section_id' => $section->id, 'is_published' => true, 'content_ar' => 'نص-سري-للدرس',
        ]);
        Lesson::factory()->create(['section_id' => $section->id, 'is_published' => false, 'title_ar' => 'درس-مسودة']);

        $this->get(route('student.courses.show', $course->slug))->assertOk()
            ->assertSee('درس-ظاهر')
            ->assertDontSee('درس-مسودة')
            ->assertDontSee('SECRET999')
            ->assertDontSee('نص-سري-للدرس');
    }

    public function test_titles_are_escaped(): void
    {
        $course = $this->free(['title_ar' => '<script>alert(1)</script>']);

        $this->get(route('student.courses.index'))->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('student.courses.show', $course->slug))->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_course_page_reflects_the_viewer_state(): void
    {
        $free = $this->free();
        $paid = $this->paid();

        $this->get(route('student.courses.show', $free->slug))->assertSee(__('student.course.enroll_free'))->assertSee(route('student.courses.continue', $free->slug));

        $user = $this->student();
        $this->actingAs($user);
        $this->get(route('student.courses.show', $free->slug))->assertSee(__('student.course.enroll_free'));
        $this->get(route('student.courses.show', $paid->slug))->assertSee(__('student.course.add_to_cart'));

        $this->post(route('student.cart.add'), ['course_id' => $paid->id]);
        $this->get(route('student.courses.show', $paid->slug))->assertSee(__('student.course.in_cart'));

        $this->actingAs($free->instructor)->get(route('student.courses.show', $free->slug))
            ->assertSee(__('student.course.you_teach'));
    }

    /* ---------- التسجيل المجاني ---------- */

    public function test_free_enrollment(): void
    {
        $user   = $this->student();
        $course = $this->free();

        $this->actingAs($user)->post(route('student.courses.enroll', $course->slug))
            ->assertRedirect(route('student.courses.mine'));

        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'active']);
        $this->get(route('student.courses.mine'))->assertOk()->assertSee($course->title_ar);
    }

    public function test_free_enrollment_rules(): void
    {
        $user = $this->student();
        $this->actingAs($user);

        $paid = $this->paid();
        $this->post(route('student.courses.enroll', $paid->slug))->assertSessionHasErrors('enroll');
        $this->assertDatabaseCount('enrollments', 0);

        $own = $this->free(['instructor_id' => $user->id]);
        $this->post(route('student.courses.enroll', $own->slug))->assertSessionHasErrors('enroll');

        $free = $this->free();
        $this->post(route('student.courses.enroll', $free->slug))->assertSessionHasNoErrors();
        $this->post(route('student.courses.enroll', $free->slug))->assertSessionHasErrors('enroll');
        $this->assertSame(1, Enrollment::where('user_id', $user->id)->where('course_id', $free->id)->count());

        $draft = Course::factory()->create();
        $this->post(route('student.courses.enroll', $draft->slug))->assertNotFound();
    }

    public function test_cancelled_enrollment_cannot_be_reclaimed_by_the_student(): void
    {
        $user   = $this->student();
        $course = $this->free();
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'cancelled']);

        $this->actingAs($user)->post(route('student.courses.enroll', $course->slug))->assertSessionHasErrors('enroll');
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'cancelled']);
    }

    public function test_my_courses_lists_only_own_active_enrollments(): void
    {
        $me     = $this->student();
        $other  = $this->student();
        $mine   = $this->free(['title_ar' => 'دورتي-خاصة']);
        $theirs = $this->free(['title_ar' => 'دورة-غيري']);
        Enrollment::factory()->create(['user_id' => $me->id, 'course_id' => $mine->id, 'status' => 'active']);
        Enrollment::factory()->create(['user_id' => $other->id, 'course_id' => $theirs->id, 'status' => 'active']);

        $this->actingAs($me)->get(route('student.courses.mine'))
            ->assertSee('دورتي-خاصة')->assertDontSee('دورة-غيري');
    }

    /* ---------- السلة والطلب ---------- */

    public function test_cart_add_and_remove(): void
    {
        $user   = $this->student();
        $course = $this->paid(['title_ar' => 'في-السلة']);
        $this->actingAs($user);

        $this->post(route('student.cart.add'), ['course_id' => $course->id])->assertRedirect(route('student.cart.show'));
        $this->get(route('student.cart.show'))->assertOk()->assertSee('في-السلة');

        $this->delete(route('student.cart.remove', $course->id))->assertRedirect(route('student.cart.show'));
        $this->assertSame(0, CartItem::count());
    }

    public function test_cart_add_errors(): void
    {
        $user = $this->student();
        $this->actingAs($user);

        $this->post(route('student.cart.add'), [])->assertSessionHasErrors('course_id');
        $this->post(route('student.cart.add'), ['course_id' => 999999])->assertNotFound();

        $free = $this->free();
        $this->post(route('student.cart.add'), ['course_id' => $free->id])->assertSessionHasErrors('cart');

        $draft = Course::factory()->paid()->create();
        $this->post(route('student.cart.add'), ['course_id' => $draft->id])->assertSessionHasErrors('cart');

        $own = $this->paid(['instructor_id' => $user->id]);
        $this->post(route('student.cart.add'), ['course_id' => $own->id])->assertSessionHasErrors('cart');

        $this->assertSame(0, CartItem::count());
    }

    public function test_one_user_cannot_remove_from_anothers_cart(): void
    {
        $owner  = $this->student();
        $course = $this->paid();
        $this->actingAs($owner)->post(route('student.cart.add'), ['course_id' => $course->id]);

        $this->actingAs($this->student())->delete(route('student.cart.remove', $course->id));
        $this->assertSame(1, CartItem::count());
    }

    public function test_checkout_creates_a_pending_order_and_shows_it(): void
    {
        $user   = $this->student();
        $course = $this->paid(['title_ar' => 'دورة-الطلب']);
        $this->actingAs($user)->post(route('student.cart.add'), ['course_id' => $course->id]);

        $response = $this->post(route('student.checkout'));
        $order    = Order::where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('student.orders.show', $order->order_number));
        $this->assertSame('pending', $order->status);

        $this->get(route('student.orders.show', $order->order_number))->assertOk()
            ->assertSee('دورة-الطلب')->assertSee($order->order_number);
        $this->get(route('student.orders.index'))->assertOk()->assertSee($order->order_number);

        // الوصول لا يُمنح قبل تأكيد الدفع
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_checkout_with_empty_cart_or_bad_coupon_returns_an_error(): void
    {
        $user = $this->student();
        $this->actingAs($user);

        $this->post(route('student.checkout'))->assertRedirect(route('student.cart.show'))->assertSessionHasErrors('checkout');

        $this->post(route('student.cart.add'), ['course_id' => $this->paid()->id]);
        $this->post(route('student.checkout'), ['coupon' => 'NO-SUCH-CODE'])->assertSessionHasErrors('checkout');
        $this->post(route('student.checkout'), ['coupon' => str_repeat('x', 51)])->assertSessionHasErrors('coupon');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_orders_are_private_to_their_owner(): void
    {
        $owner = $this->student();
        $this->actingAs($owner)->post(route('student.cart.add'), ['course_id' => $this->paid()->id]);
        $this->post(route('student.checkout'));
        $order = Order::firstOrFail();

        $intruder = $this->student();
        $this->actingAs($intruder);
        $this->get(route('student.orders.show', $order->order_number))->assertNotFound();
        $this->get(route('student.orders.index'))->assertDontSee($order->order_number);

        $this->get('/my/orders/' . $order->id)->assertNotFound();
        $this->get('/my/orders/ORD-ZZZZZZZZ')->assertNotFound();
    }

    public function test_guest_browses_freely_and_is_asked_to_log_in_only_to_buy(): void
    {
        $course = $this->paid();

        $this->get(route('student.home'))->assertOk();
        $this->get(route('student.courses.index'))->assertOk();
        $this->get(route('student.courses.show', $course->slug))->assertOk()
            ->assertSee(route('student.courses.continue', $course->slug), false);

        $this->get(route('student.courses.continue', $course->slug))->assertRedirect(route('login'));

        $user = $this->student();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('student.courses.continue', $course->slug));
        $this->get(route('student.courses.continue', $course->slug))
            ->assertRedirect(route('student.courses.show', $course->slug));
    }
}
