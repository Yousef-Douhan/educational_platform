<?php

namespace Tests\Feature;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CartModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'edit'] as $a) {
            Permission::findOrCreate("carts.{$a}", 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function service(): CartService
    {
        return app(CartService::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::all());

        return $u;
    }

    private function course(array $o = []): Course
    {
        return Course::factory()->published()->paid()->create($o);
    }

    private function fill(Cart $cart, Course ...$courses): void
    {
        foreach ($courses as $c) {
            CartItem::factory()->create(['cart_id' => $cart->id, 'course_id' => $c->id]);
        }
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (CartException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected CartException: {$reason}");
    }

    /* ---------- السلة والقيود ---------- */

    public function test_each_user_gets_exactly_one_cart(): void
    {
        $user = User::factory()->create();

        $first  = $this->service()->forUser($user);
        $second = $this->service()->forUser($user);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Cart::count());
    }

    public function test_database_enforces_one_cart_per_user_and_one_row_per_course(): void
    {
        $cart = Cart::factory()->create();
        $c    = $this->course();
        $this->fill($cart, $c);

        try {
            CartItem::create(['cart_id' => $cart->id, 'course_id' => $c->id]);
            $this->fail('duplicate course allowed');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, $cart->items()->count());
        }

        $this->expectException(UniqueConstraintViolationException::class);
        Cart::create(['user_id' => $cart->user_id]);
    }

    public function test_deleting_a_course_for_good_removes_its_cart_items(): void
    {
        $cart = Cart::factory()->create();
        $c    = $this->course();
        $this->fill($cart, $c);

        $c->forceDelete();

        $this->assertSame(0, CartItem::count());
    }

    /* ---------- الإضافة ---------- */

    public function test_add_puts_a_paid_published_course_in_the_cart_and_touches_it(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->idleFor(10)->create(['user_id' => $user->id]);
        $c    = $this->course();

        $item = $this->service()->add($user, $c);

        $this->assertSame($c->id, $item->course_id);
        $this->assertSame($cart->id, $item->cart_id);
        $this->assertTrue($cart->fresh()->updated_at->gt(now()->subMinute()));
    }

    public function test_add_creates_the_cart_when_missing(): void
    {
        $user = User::factory()->create();

        $this->service()->add($user, $this->course());

        $this->assertSame(1, Cart::where('user_id', $user->id)->count());
    }

    public function test_add_rejects_the_same_course_twice(): void
    {
        $user = User::factory()->create();
        $c    = $this->course();

        $this->service()->add($user, $c);

        $this->assertRejected('already_in_cart', fn () => $this->service()->add($user, $c));
        $this->assertSame(1, CartItem::count());
    }

    public function test_add_rejects_courses_that_are_not_published(): void
    {
        $user = User::factory()->create();

        foreach (['draft', 'pending_review', 'archived'] as $status) {
            $c = $this->course(['status' => $status]);
            $this->assertRejected('course_unavailable', fn () => $this->service()->add($user, $c));
        }

        $trashed = $this->course();
        $trashed->delete();
        $this->assertRejected('course_unavailable', fn () => $this->service()->add($user, $trashed));

        $this->assertSame(0, CartItem::count());
    }

    public function test_add_rejects_free_courses(): void
    {
        $free = Course::factory()->published()->create(['price' => 0]);

        $this->assertRejected('free_course', fn () => $this->service()->add(User::factory()->create(), $free));
    }

    public function test_add_rejects_the_courses_own_instructors(): void
    {
        $teacher = User::factory()->create();
        $own     = $this->course(['instructor_id' => $teacher->id]);

        $other = $this->course();
        $other->coInstructors()->attach($teacher->id);

        $this->assertRejected('own_course', fn () => $this->service()->add($teacher, $own));
        $this->assertRejected('own_course', fn () => $this->service()->add($teacher, $other));
    }

    public function test_add_rejects_inactive_accounts(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->assertRejected('account_inactive', fn () => $this->service()->add($user, $this->course()));
    }

    public function test_add_rejects_students_who_already_have_access(): void
    {
        $user = User::factory()->create();

        $active = $this->course();
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $active->id]);

        $completed = $this->course();
        Enrollment::factory()->status('completed')->create(['user_id' => $user->id, 'course_id' => $completed->id]);

        $this->assertRejected('already_enrolled', fn () => $this->service()->add($user, $active));
        $this->assertRejected('already_enrolled', fn () => $this->service()->add($user, $completed));
    }

    public function test_add_allows_courses_whose_old_enrollment_gives_no_access(): void
    {
        $user = User::factory()->create();

        $cancelled = $this->course();
        Enrollment::factory()->status('cancelled')->create(['user_id' => $user->id, 'course_id' => $cancelled->id]);

        $expired = $this->course();
        Enrollment::factory()->status('expired')->create(['user_id' => $user->id, 'course_id' => $expired->id]);

        $lapsed = $this->course();
        Enrollment::factory()->expiresAt(now()->subDay())->create(['user_id' => $user->id, 'course_id' => $lapsed->id]);

        foreach ([$cancelled, $expired, $lapsed] as $c) {
            $this->service()->add($user, $c);
        }

        $this->assertSame(3, CartItem::count());
    }

    public function test_cart_has_a_maximum_size(): void
    {
        $user = User::factory()->create();
        $cart = $this->service()->forUser($user);

        $courses = Course::factory()->published()->paid()->count(Cart::MAX_ITEMS)->create();
        foreach ($courses as $c) {
            $this->fill($cart, $c);
        }

        $this->assertRejected('cart_full', fn () => $this->service()->add($user, $this->course()));
        $this->assertSame(Cart::MAX_ITEMS, $cart->items()->count());
    }

    public function test_rejected_adds_leave_no_trace(): void
    {
        $user = User::factory()->create();

        try {
            $this->service()->add($user, $this->course(['status' => 'draft']));
        } catch (CartException) {
        }

        $this->assertSame(0, CartItem::count());
    }

    /* ---------- الحذف والتفريغ ---------- */

    public function test_remove_deletes_the_item_and_touches_the_cart(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->idleFor(10)->create(['user_id' => $user->id]);
        $a    = $this->course();
        $b    = $this->course();
        $this->fill($cart, $a, $b);

        $this->assertTrue($this->service()->remove($user, $a));
        $this->assertEquals([$b->id], $cart->items()->pluck('course_id')->all());
        $this->assertTrue($cart->fresh()->updated_at->gt(now()->subMinute()));
    }

    public function test_remove_accepts_an_id_and_reports_missing_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $a    = $this->course();
        $this->fill($cart, $a);

        $this->assertFalse($this->service()->remove($user, $this->course()));
        $this->assertTrue($this->service()->remove($user, $a->id));
        $this->assertFalse($this->service()->remove($user, $a->id));
    }

    public function test_remove_without_a_cart_is_harmless(): void
    {
        $this->assertFalse($this->service()->remove(User::factory()->create(), $this->course()));
    }

    public function test_a_user_cannot_remove_from_another_users_cart(): void
    {
        $owner = User::factory()->create();
        $cart  = Cart::factory()->create(['user_id' => $owner->id]);
        $c     = $this->course();
        $this->fill($cart, $c);

        $this->assertFalse($this->service()->remove(User::factory()->create(), $c));
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_clear_empties_the_cart_and_counts(): void
    {
        $cart = Cart::factory()->idleFor(10)->create();
        $this->fill($cart, $this->course(), $this->course());

        $this->assertSame(2, $this->service()->clear($cart));
        $this->assertSame(0, $cart->items()->count());
        $this->assertTrue($cart->fresh()->updated_at->gt(now()->subMinute()));
        $this->assertSame(0, $this->service()->clear($cart));
    }

    /* ---------- الفحص والمجموع ---------- */

    public function test_inspect_flags_each_kind_of_invalid_item(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $ok         = $this->course(['price' => 50]);
        $draft      = $this->course();
        $trashed    = $this->course();
        $own        = $this->course();
        $enrolled   = $this->course();
        $nowFree    = $this->course();
        $this->fill($cart, $ok, $draft, $trashed, $own, $enrolled, $nowFree);

        $draft->update(['status' => 'draft']);
        $trashed->delete();
        $own->update(['instructor_id' => $user->id]);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $enrolled->id]);
        $nowFree->update(['price' => 0]);

        $issues = $this->service()->inspect($cart)->mapWithKeys(fn ($r) => [$r['course']->id => $r['issue']]);

        $this->assertNull($issues[$ok->id]);
        $this->assertSame('course_unavailable', $issues[$draft->id]);
        $this->assertSame('course_unavailable', $issues[$trashed->id]);
        $this->assertSame('own_course', $issues[$own->id]);
        $this->assertSame('already_enrolled', $issues[$enrolled->id]);
        $this->assertSame('became_free', $issues[$nowFree->id]);
    }

    public function test_inspect_flags_co_taught_courses(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $c    = $this->course();
        $this->fill($cart, $c);
        $c->coInstructors()->attach($user->id);

        $this->assertSame('own_course', $this->service()->inspect($cart)->first()['issue']);
    }

    public function test_prices_follow_the_course_and_use_the_discount(): void
    {
        $cart = Cart::factory()->create();
        $c    = $this->course(['price' => 100, 'discount_price' => 80]);
        $this->fill($cart, $c);

        $this->assertSame('80.00', $this->service()->inspect($cart)->first()['price']);

        $c->update(['discount_price' => null, 'price' => 120]);

        $this->assertSame('120.00', $this->service()->inspect($cart)->first()['price']);
    }

    public function test_subtotal_counts_valid_items_only_and_is_exact(): void
    {
        $cart = Cart::factory()->create();
        $a    = $this->course(['price' => 10.10]);
        $b    = $this->course(['price' => 20.20]);
        $bad  = $this->course(['price' => 999]);
        $this->fill($cart, $a, $b, $bad);
        $bad->update(['status' => 'archived']);

        $rows = $this->service()->inspect($cart);

        $this->assertSame('30.30', $this->service()->subtotal($rows));
        $this->assertSame('0.00', $this->service()->subtotal(collect()));
    }

    public function test_prepare_for_checkout_removes_invalid_items_and_reports_them(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->idleFor(10)->create(['user_id' => $user->id]);
        $good = $this->course(['price' => 40]);
        $gone = $this->course(['price' => 60]);
        $this->fill($cart, $good, $gone);
        $gone->delete();

        $result = $this->service()->prepareForCheckout($user);

        $this->assertSame('40.00', $result['subtotal']);
        $this->assertEquals([$good->id], $result['items']->map(fn ($r) => $r['course']->id)->all());
        $this->assertSame(['course_unavailable'], $result['removed']->pluck('issue')->all());
        $this->assertEquals([$good->id], $cart->items()->pluck('course_id')->all());
        $this->assertTrue($cart->fresh()->updated_at->gt(now()->subMinute()));
    }

    public function test_prepare_for_checkout_with_an_empty_cart_returns_no_items(): void
    {
        $result = $this->service()->prepareForCheckout(User::factory()->create());

        $this->assertTrue($result['items']->isEmpty());
        $this->assertSame('0.00', $result['subtotal']);
    }

    public function test_errors_are_translated_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (['account_inactive', 'course_unavailable', 'own_course', 'free_course', 'already_enrolled', 'already_in_cart', 'cart_full'] as $reason) {
                $this->assertNotSame("carts.errors.{$reason}", CartException::because($reason)->getMessage());
            }
        }
    }

    /* ---------- الحالات ---------- */

    public function test_cart_states_and_the_abandoned_boundary(): void
    {
        $empty = Cart::factory()->idleFor(30)->create();

        $fresh = Cart::factory()->create();
        $this->fill($fresh, $this->course());

        $edge = Cart::factory()->create(['updated_at' => now()->subDays(Cart::ABANDONED_AFTER_DAYS)->addMinute()]);
        $this->fill($edge, $this->course());

        $old = Cart::factory()->create(['updated_at' => now()->subDays(Cart::ABANDONED_AFTER_DAYS)->subMinute()]);
        $this->fill($old, $this->course());

        $withCounts = fn ($c) => Cart::withCount('items')->find($c->id);

        $this->assertSame('empty', $withCounts($empty)->state());
        $this->assertSame('active', $withCounts($fresh)->state());
        $this->assertSame('active', $withCounts($edge)->state());
        $this->assertSame('abandoned', $withCounts($old)->state());

        foreach (Cart::STATES as $state) {
            $expected = Cart::withCount('items')->get()->filter(fn ($c) => $c->state() === $state)->pluck('id')->sort()->values()->all();
            $actual   = Cart::inState($state)->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, "scope mismatch for {$state}");
        }
    }

    /* ---------- لوحة الإدارة ---------- */

    public function test_guests_and_users_without_permission_are_blocked(): void
    {
        $cart = Cart::factory()->create();

        $this->get(route('cms.admin.carts.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->getJson(route('cms.admin.carts.index'))->assertForbidden();
        $this->getJson(route('cms.admin.carts.show', $cart))->assertForbidden();
        $this->postJson(route('cms.admin.carts.clear', $cart))->assertForbidden();
    }

    public function test_view_permission_cannot_clear_carts(): void
    {
        $cart = Cart::factory()->create();
        $this->fill($cart, $this->course());

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('carts.view');
        $this->actingAs($viewer);

        $this->getJson(route('cms.admin.carts.index'))->assertOk();
        $this->postJson(route('cms.admin.carts.clear', $cart))->assertForbidden();
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_there_is_no_route_to_create_edit_or_delete_carts(): void
    {
        $cart = Cart::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson('/cms/admin/carts')->assertStatus(405);
        $this->putJson(route('cms.admin.carts.show', $cart))->assertStatus(405);
        $this->deleteJson(route('cms.admin.carts.show', $cart))->assertStatus(405);
    }

    public function test_index_page_renders(): void
    {
        $this->withoutVite();
        $cart = Cart::factory()->create();
        $this->fill($cart, $this->course());

        $this->actingAs($this->admin())->get(route('cms.admin.carts.index'))
            ->assertOk()->assertSee($cart->user->email);
    }

    public function test_index_json_returns_real_stats_totals_and_filters(): void
    {
        $active = Cart::factory()->create();
        $this->fill($active, $this->course(['price' => 100, 'discount_price' => 80]), $this->course(['price' => 20]));

        $abandoned = Cart::factory()->idleFor(20)->create();
        $this->fill($abandoned, $this->course(['price' => 55]));

        Cart::factory()->create(); // فارغة
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.index'))->assertOk()
            ->assertJsonPath('stats', ['total' => 3, 'active' => 1, 'abandoned' => 1, 'empty' => 1])
            ->assertSee('100.00')   // 80 + 20 بالخصم
            ->assertSee('55.00');

        $this->getJson(route('cms.admin.carts.index', ['state' => 'abandoned']))->assertOk()
            ->assertSee($abandoned->user->email, false)->assertDontSee($active->user->email, false);

        $this->getJson(route('cms.admin.carts.index', ['state' => 'empty']))->assertOk()
            ->assertDontSee($active->user->email, false);
    }

    public function test_index_search_by_name_or_email_and_wildcards_are_literal(): void
    {
        $a = Cart::factory()->create(['user_id' => User::factory()->create(['name' => 'Layla Hassan', 'email' => 'layla@example.test'])->id]);
        $b = Cart::factory()->create(['user_id' => User::factory()->create(['name' => 'Omar Said', 'email' => 'omar@example.test'])->id]);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.index', ['search' => 'layla']))->assertOk()
            ->assertSee('layla@example.test', false)->assertDontSee('omar@example.test', false);

        $this->getJson(route('cms.admin.carts.index', ['search' => 'omar@']))->assertOk()
            ->assertSee('omar@example.test', false)->assertDontSee('layla@example.test', false);

        $this->assertSame(0, Cart::search('%')->count());
    }

    public function test_index_search_finds_carts_of_deleted_users(): void
    {
        $user = User::factory()->create(['name' => 'Ghost User', 'email' => 'ghost@example.test']);
        Cart::factory()->create(['user_id' => $user->id]);
        $user->delete();
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.index', ['search' => 'ghost']))->assertOk()
            ->assertSee('ghost@example.test', false);
    }

    public function test_index_sort_is_whitelisted_and_total_sort_works(): void
    {
        $small = Cart::factory()->create();
        $this->fill($small, $this->course(['price' => 10]));
        $big = Cart::factory()->create();
        $this->fill($big, $this->course(['price' => 500]));
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.index', ['sort' => 'x; drop table carts', 'dir' => 'zz']))->assertOk();

        $html = $this->getJson(route('cms.admin.carts.index', ['sort' => 'total', 'dir' => 'desc']))->assertOk()->json('html');
        $this->assertLessThan(strpos($html, '10.00'), strpos($html, '500.00'));
    }

    public function test_show_returns_items_with_issues_and_subtotal(): void
    {
        $cart = Cart::factory()->create();
        $good = $this->course(['price' => 70, 'title_en' => 'Good course']);
        $bad  = $this->course(['price' => 30]);
        $this->fill($cart, $good, $bad);
        $bad->update(['status' => 'archived']);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.show', $cart))->assertOk()
            ->assertJsonPath('data.user.email', $cart->user->email)
            ->assertJsonPath('data.subtotal', '70.00')
            ->assertJsonPath('data.state', 'active')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.title_en', 'Good course')
            ->assertJsonPath('data.items.0.issue', null)
            ->assertJsonPath('data.items.1.issue', 'course_unavailable');
    }

    public function test_show_works_for_deleted_users_and_courses(): void
    {
        $cart = Cart::factory()->create();
        $c    = $this->course();
        $this->fill($cart, $c);
        $c->delete();
        $cart->user->delete();
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.show', $cart))->assertOk()
            ->assertJsonPath('data.user.deleted', true)
            ->assertJsonPath('data.items.0.issue', 'course_unavailable');
    }

    public function test_clear_empties_the_cart_and_returns_fresh_stats(): void
    {
        $cart = Cart::factory()->idleFor(20)->create();
        $this->fill($cart, $this->course(), $this->course());
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.carts.clear', $cart))->assertOk()
            ->assertJsonPath('stats.abandoned', 0)
            ->assertJsonPath('stats.empty', 1);

        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(1, Cart::count()); // السلة نفسها تبقى
    }

    public function test_carts_of_unknown_ids_return_404(): void
    {
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.carts.show', 999999))->assertNotFound();
        $this->postJson(route('cms.admin.carts.clear', 999999))->assertNotFound();
    }

    public function test_one_query_per_page_for_totals_no_lazy_loading(): void
    {
        foreach (range(1, 6) as $_) {
            $cart = Cart::factory()->create();
            $this->fill($cart, $this->course(), $this->course());
        }
        $this->actingAs($this->admin());

        DB::enableQueryLog();
        $this->getJson(route('cms.admin.carts.index', ['per_page' => 10]))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // لا يتضاعف العدد مع عدد السلات (N+1)
        $this->assertLessThan(30, $queries);
    }
}
