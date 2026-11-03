<?php

namespace Tests\Feature;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CouponModuleTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            Permission::findOrCreate("coupons.{$a}", 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::all());

        return $u;
    }

    private function userWith(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'code'           => 'SUMMER-2026',
            'discount_type'  => 'percentage',
            'discount_value' => '20',
            'is_active'      => 1,
        ], $o);
    }

    private function create(array $o = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(route('cms.admin.coupons.store'), $this->payload($o));
    }

    private function used(Coupon $c, int $times = 2): Coupon
    {
        $c->forceFill(['times_used' => $times])->save();

        return $c->fresh();
    }

    /* ---------- الصلاحيات ---------- */

    public function test_guests_are_redirected_and_users_without_permission_are_forbidden(): void
    {
        $this->get(route('cms.admin.coupons.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->getJson(route('cms.admin.coupons.index'))->assertForbidden();
    }

    public function test_each_action_needs_its_own_permission(): void
    {
        $c = Coupon::factory()->create();
        $viewer = $this->userWith('coupons.view');
        $this->actingAs($viewer);

        $this->getJson(route('cms.admin.coupons.index'))->assertOk();
        $this->postJson(route('cms.admin.coupons.store'), $this->payload())->assertForbidden();
        $this->putJson(route('cms.admin.coupons.update', $c), $this->payload())->assertForbidden();
        $this->patchJson(route('cms.admin.coupons.toggle', $c))->assertForbidden();
        $this->deleteJson(route('cms.admin.coupons.destroy', $c))->assertForbidden();
    }

    public function test_index_page_renders_for_html_requests(): void
    {
        $this->withoutVite();
        Coupon::factory()->create(['code' => 'HELLO-1']);

        $this->actingAs($this->admin())->get(route('cms.admin.coupons.index'))
            ->assertOk()->assertSee('HELLO-1');
    }

    /* ---------- الإنشاء والتحقق ---------- */

    public function test_store_creates_a_coupon_and_normalizes_the_code(): void
    {
        $this->create(['code' => '  summer-2026 ', 'discount_value' => '15.5'])
            ->assertCreated()->assertJsonPath('data.code', 'SUMMER-2026');

        $c = Coupon::firstOrFail();
        $this->assertSame('SUMMER-2026', $c->code);
        $this->assertSame('15.50', $c->discount_value);
        $this->assertSame(0, $c->times_used);
        $this->assertTrue($c->is_active);
    }

    public function test_times_used_cannot_be_set_from_the_request(): void
    {
        $this->create(['times_used' => 99])->assertCreated();

        $this->assertSame(0, Coupon::firstOrFail()->times_used);
    }

    public function test_code_must_be_unique_ignoring_case_and_including_trashed(): void
    {
        Coupon::factory()->create(['code' => 'DUP-1']);
        Coupon::factory()->create(['code' => 'GONE-1'])->delete();

        $this->create(['code' => 'dup-1'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->create(['code' => 'GONE-1'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_code_format_is_validated(): void
    {
        foreach (['AB', '-BAD', 'has space', 'عربي-1', 'a/b', str_repeat('A', 51)] as $bad) {
            $this->create(['code' => $bad])->assertUnprocessable()->assertJsonValidationErrors('code');
        }
    }

    public function test_discount_value_rules_depend_on_type(): void
    {
        $this->create(['discount_value' => '100.01'])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->create(['discount_value' => '0'])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->create(['discount_value' => '-5'])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->create(['discount_value' => '10.999'])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->create(['discount_type' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('discount_type');

        // المبلغ الثابت يتجاوز 100 دون مشكلة
        $this->create(['code' => 'FIX-500', 'discount_type' => 'fixed', 'discount_value' => '500'])->assertCreated();
        $this->create(['code' => 'PCT-100', 'discount_value' => '100'])->assertCreated();
    }

    public function test_max_discount_is_dropped_for_fixed_coupons(): void
    {
        $this->create(['code' => 'FIX-1', 'discount_type' => 'fixed', 'discount_value' => '10', 'max_discount' => '5'])->assertCreated();
        $this->create(['code' => 'PCT-1', 'max_discount' => '50'])->assertCreated();

        $this->assertNull(Coupon::findByCode('FIX-1')->max_discount);
        $this->assertSame('50.00', Coupon::findByCode('PCT-1')->max_discount);
    }

    public function test_dates_and_limits_are_validated(): void
    {
        $past = now()->subDay()->format('Y-m-d H:i:s');
        $in2  = now()->addDays(2)->format('Y-m-d H:i:s');
        $in5  = now()->addDays(5)->format('Y-m-d H:i:s');

        $this->create(['expires_at' => $past])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->create(['starts_at' => $in5, 'expires_at' => $in2])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->create(['usage_limit' => 0])->assertUnprocessable()->assertJsonValidationErrors('usage_limit');
        $this->create(['per_user_limit' => 0])->assertUnprocessable()->assertJsonValidationErrors('per_user_limit');
        $this->create(['usage_limit' => 5, 'per_user_limit' => 6])->assertUnprocessable()->assertJsonValidationErrors('per_user_limit');
        $this->create(['min_order_amount' => -1])->assertUnprocessable()->assertJsonValidationErrors('min_order_amount');

        $this->create(['starts_at' => $in2, 'expires_at' => $in5, 'usage_limit' => 5, 'per_user_limit' => 5])->assertCreated();
    }

    public function test_empty_optional_fields_are_stored_as_null(): void
    {
        $this->create(['max_discount' => '', 'min_order_amount' => '', 'starts_at' => '', 'expires_at' => '', 'usage_limit' => '', 'per_user_limit' => ''])
            ->assertCreated();

        $c = Coupon::firstOrFail();
        $this->assertNull($c->max_discount);
        $this->assertNull($c->min_order_amount);
        $this->assertNull($c->starts_at);
        $this->assertNull($c->expires_at);
        $this->assertNull($c->usage_limit);
        $this->assertNull($c->per_user_limit);
    }

    /* ---------- التعديل ---------- */

    public function test_unused_coupon_can_be_edited_freely(): void
    {
        $c = Coupon::factory()->create(['code' => 'OLD-1']);

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload([
            'code' => 'NEW-1',
            'discount_type' => 'fixed',
            'discount_value' => '7.5',
            'usage_limit' => 10,
        ]))->assertOk();

        $c->refresh();
        $this->assertSame('NEW-1', $c->code);
        $this->assertSame('fixed', $c->discount_type);
        $this->assertSame('7.50', $c->discount_value);
        $this->assertSame(10, $c->usage_limit);
    }

    public function test_updating_with_its_own_code_is_not_a_duplicate(): void
    {
        $c = Coupon::factory()->create(['code' => 'KEEP-1']);

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload(['code' => 'keep-1']))
            ->assertOk();
    }

    public function test_updating_may_keep_a_past_expiry_date(): void
    {
        $c = Coupon::factory()->expired()->create();

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload([
            'code' => $c->code,
            'expires_at' => $c->expires_at->format('Y-m-d H:i:s'),
        ]))->assertOk();
    }

    public function test_used_coupon_locks_code_type_and_value(): void
    {
        $c = $this->used(Coupon::factory()->create(['code' => 'USED-1', 'discount_value' => 10]));
        $this->actingAs($this->admin());
        $url = route('cms.admin.coupons.update', $c);

        $this->putJson($url, $this->payload(['code' => 'OTHER-1', 'discount_value' => '10']))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson($url, $this->payload(['code' => 'USED-1', 'discount_value' => '25']))
            ->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->putJson($url, $this->payload(['code' => 'USED-1', 'discount_type' => 'fixed', 'discount_value' => '10']))
            ->assertUnprocessable()->assertJsonValidationErrors('discount_type');

        $c->refresh();
        $this->assertSame('USED-1', $c->code);
        $this->assertSame('10.00', $c->discount_value);
        $this->assertSame('percentage', $c->discount_type);
    }

    public function test_used_coupon_still_allows_other_fields_and_same_value_formats(): void
    {
        $c = $this->used(Coupon::factory()->create(['code' => 'USED-2', 'discount_value' => 10]));

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload([
            'code' => 'USED-2',
            'discount_value' => '10.0',
            'min_order_amount' => '30',
            'usage_limit' => 50,
            'is_active' => 0,
        ]))->assertOk();

        $c->refresh();
        $this->assertSame('30.00', $c->min_order_amount);
        $this->assertSame(50, $c->usage_limit);
        $this->assertFalse($c->is_active);
        $this->assertSame(2, $c->times_used);
    }

    public function test_usage_limit_cannot_drop_below_times_used(): void
    {
        $c = $this->used(Coupon::factory()->create(['code' => 'USED-3', 'discount_value' => 20]), 5);

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload(['code' => 'USED-3', 'usage_limit' => 4]))
            ->assertUnprocessable()->assertJsonValidationErrors('usage_limit');

        $this->actingAs($this->admin())->putJson(route('cms.admin.coupons.update', $c), $this->payload(['code' => 'USED-3', 'usage_limit' => 5]))
            ->assertOk();
    }

    public function test_show_and_edit_return_form_ready_data(): void
    {
        $c = Coupon::factory()->create(['starts_at' => '2030-01-02 09:30:00', 'expires_at' => '2030-02-03 18:45:00']);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.coupons.edit', $c))->assertOk()
            ->assertJsonPath('data.starts_at_input', '2030-01-02T09:30')
            ->assertJsonPath('data.expires_at_label', '2030-02-03 18:45')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.has_been_used', false);

        $this->getJson(route('cms.admin.coupons.show', $c))->assertOk()->assertJsonPath('data.code', $c->code);
    }

    /* ---------- التفعيل والحذف ---------- */

    public function test_toggle_flips_the_active_flag(): void
    {
        $c = Coupon::factory()->create();
        $this->actingAs($this->admin());

        $this->patchJson(route('cms.admin.coupons.toggle', $c))->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->assertFalse($c->fresh()->is_active);

        $this->patchJson(route('cms.admin.coupons.toggle', $c))->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertTrue($c->fresh()->is_active);
    }

    public function test_soft_delete_restore_and_trash_listing(): void
    {
        $c = Coupon::factory()->create(['code' => 'TRASH-1']);
        $this->actingAs($this->admin());

        $this->deleteJson(route('cms.admin.coupons.destroy', $c))->assertOk();
        $this->assertSoftDeleted($c);
        $this->assertNull(Coupon::findByCode('TRASH-1'));

        $this->getJson(route('cms.admin.coupons.trashed'))->assertOk()
            ->assertJsonPath('stats.trashed', 1)
            ->assertSee('TRASH-1');

        $this->patchJson(route('cms.admin.coupons.restore', $c->id))->assertOk();
        $this->assertNotNull(Coupon::findByCode('TRASH-1'));
    }

    public function test_force_delete_is_blocked_for_used_coupons_only(): void
    {
        $used = $this->used(Coupon::factory()->create());
        $free = Coupon::factory()->create();
        $used->delete();
        $free->delete();
        $this->actingAs($this->admin());

        $this->deleteJson(route('cms.admin.coupons.force-delete', $used->id))->assertStatus(409);
        $this->assertDatabaseHas('coupons', ['id' => $used->id]);

        $this->deleteJson(route('cms.admin.coupons.force-delete', $free->id))->assertOk();
        $this->assertDatabaseMissing('coupons', ['id' => $free->id]);
    }

    public function test_force_delete_and_restore_ignore_active_records(): void
    {
        $live = Coupon::factory()->create();
        $this->actingAs($this->admin());

        $this->deleteJson(route('cms.admin.coupons.force-delete', $live->id))->assertNotFound();
        $this->patchJson(route('cms.admin.coupons.restore', $live->id))->assertNotFound();
    }

    /* ---------- القائمة: بحث وفلاتر وإحصائيات ---------- */

    public function test_search_filters_and_stats(): void
    {
        Coupon::factory()->create(['code' => 'ALPHA-1']);
        Coupon::factory()->fixed()->create(['code' => 'BETA-1']);
        Coupon::factory()->expired()->create(['code' => 'OLD-1']);
        Coupon::factory()->limit(2, 2)->create(['code' => 'FULL-1']);
        Coupon::factory()->create()->delete();
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.coupons.index', ['search' => 'alp']))->assertOk()
            ->assertSee('ALPHA-1')->assertDontSee('BETA-1');

        $this->getJson(route('cms.admin.coupons.index', ['type' => 'fixed']))->assertOk()
            ->assertSee('BETA-1')->assertDontSee('ALPHA-1');

        $this->getJson(route('cms.admin.coupons.index', ['status' => 'expired']))->assertOk()
            ->assertSee('OLD-1')->assertDontSee('ALPHA-1');

        $this->getJson(route('cms.admin.coupons.index'))->assertOk()
            ->assertJsonPath('stats', ['total' => 4, 'active' => 2, 'expired' => 1, 'exhausted' => 1, 'trashed' => 1]);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Coupon::factory()->create(['code' => 'ONE-1']);

        $this->assertSame(0, Coupon::search('%')->count());
        $this->assertSame(0, Coupon::search('_')->count());
    }

    public function test_sort_parameter_is_whitelisted(): void
    {
        Coupon::factory()->count(2)->create();

        $this->actingAs($this->admin())->getJson(route('cms.admin.coupons.index', ['sort' => 'password; drop table', 'dir' => 'x']))
            ->assertOk();
    }

    /* ---------- الحالات ---------- */

    public function test_status_priority_and_sql_scope_agree(): void
    {
        $coupons = [
            'active'    => Coupon::factory()->create(),
            'scheduled' => Coupon::factory()->scheduled()->create(),
            'expired'   => Coupon::factory()->expired()->create(),
            'exhausted' => Coupon::factory()->limit(3, 3)->create(),
            'disabled'  => Coupon::factory()->disabled()->create(),
        ];
        // حالات متداخلة: الأولوية معطّل ثم منتهٍ ثم مستنفد ثم لم يبدأ
        $disabledAndExpired  = Coupon::factory()->disabled()->expired()->create();
        $expiredAndExhausted = Coupon::factory()->expired()->limit(1, 1)->create();
        $exhaustedScheduled  = Coupon::factory()->scheduled()->limit(1, 1)->create();

        foreach ($coupons as $expected => $c) {
            $this->assertSame($expected, $c->status());
        }
        $this->assertSame('disabled', $disabledAndExpired->status());
        $this->assertSame('expired', $expiredAndExhausted->status());
        $this->assertSame('exhausted', $exhaustedScheduled->status());

        foreach (Coupon::STATUSES as $status) {
            $expectedIds = Coupon::all()->filter(fn($c) => $c->status() === $status)->pluck('id')->sort()->values()->all();
            $actualIds   = Coupon::withStatus($status)->pluck('id')->sort()->values()->all();

            $this->assertSame($expectedIds, $actualIds, "scope mismatch for {$status}");
        }
    }

    public function test_expiry_boundary_is_exclusive(): void
    {
        $this->assertSame('expired', Coupon::factory()->make(['expires_at' => now()->subSecond()])->status());
        $this->assertSame('active', Coupon::factory()->make(['expires_at' => now()->addMinute()])->status());
    }

    /* ---------- حساب الخصم ---------- */

    public function test_percentage_discount_math_and_cap(): void
    {
        $pct = Coupon::factory()->percentage(20)->make();
        $this->assertSame('20.00', $pct->discountFor('100'));
        $this->assertSame('3.33', Coupon::factory()->percentage(33.33)->make()->discountFor('10'));
        $this->assertSame('0.02', Coupon::factory()->percentage(10)->make()->discountFor('0.15')); // 0.015 تُقرَّب لأعلى

        $capped = Coupon::factory()->percentage(50)->make(['max_discount' => 30]);
        $this->assertSame('30.00', $capped->discountFor('200'));
        $this->assertSame('25.00', $capped->discountFor('50'));
    }

    public function test_fixed_discount_never_exceeds_the_subtotal(): void
    {
        $fixed = Coupon::factory()->fixed(50)->make();

        $this->assertSame('50.00', $fixed->discountFor('120'));
        $this->assertSame('30.00', $fixed->discountFor('30'));
        $this->assertSame('0.00', $fixed->discountFor('0'));
        $this->assertSame('0.00', $fixed->discountFor('-10'));
    }

    public function test_hundred_percent_makes_the_order_free_but_not_negative(): void
    {
        $this->assertSame('80.00', Coupon::factory()->percentage(100)->make()->discountFor('80'));
    }

    /* ---------- الخدمة ---------- */

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (CouponException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected CouponException: {$reason}");
    }

    public function test_service_check_accepts_valid_codes_case_insensitively(): void
    {
        Coupon::factory()->create(['code' => 'GOOD-1']);

        $this->assertSame('GOOD-1', $this->service()->check(' good-1 ', 100)->code);
    }

    public function test_service_check_rejects_each_invalid_state(): void
    {
        Coupon::factory()->disabled()->create(['code' => 'OFF-1']);
        Coupon::factory()->expired()->create(['code' => 'EXP-1']);
        Coupon::factory()->limit(1, 1)->create(['code' => 'FULL-1']);
        Coupon::factory()->scheduled()->create(['code' => 'SOON-1']);
        Coupon::factory()->create(['code' => 'MIN-1', 'min_order_amount' => 50]);
        Coupon::factory()->create(['code' => 'TRASHED-1'])->delete();

        $this->assertRejected('not_found', fn() => $this->service()->check('NOPE', 100));
        $this->assertRejected('not_found', fn() => $this->service()->check('TRASHED-1', 100));
        $this->assertRejected('disabled', fn() => $this->service()->check('OFF-1', 100));
        $this->assertRejected('expired', fn() => $this->service()->check('EXP-1', 100));
        $this->assertRejected('exhausted', fn() => $this->service()->check('FULL-1', 100));
        $this->assertRejected('not_started', fn() => $this->service()->check('SOON-1', 100));
        $this->assertRejected('min_order', fn() => $this->service()->check('MIN-1', 49.99));

        $this->assertSame('MIN-1', $this->service()->check('MIN-1', 50)->code);
    }

    public function test_exception_messages_are_translated(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            $e = CouponException::because('expired');

            $this->assertNotSame('coupons.errors.expired', $e->getMessage());
            $this->assertNotSame('expired', $e->getMessage());
        }
    }

    public function test_consume_increments_and_stops_at_the_limit(): void
    {
        $c = Coupon::factory()->limit(2)->create();

        $this->service()->consume($c);
        $this->service()->consume($c);
        $this->assertSame(2, $c->fresh()->times_used);

        $this->assertRejected('unavailable', fn() => $this->service()->consume($c));
        $this->assertSame(2, $c->fresh()->times_used);
    }

    public function test_consume_uses_the_database_state_not_a_stale_model(): void
    {
        $c     = Coupon::factory()->limit(1)->create();
        $stale = Coupon::find($c->id);

        $this->service()->consume($c);

        // النسخة القديمة ما زالت تظن أن العدد 0، لكن التحديث الذرّي يرفض
        $this->assertSame(0, $stale->times_used);
        $this->assertRejected('unavailable', fn() => $this->service()->consume($stale));
        $this->assertSame(1, $c->fresh()->times_used);
    }

    public function test_consume_rejects_expired_disabled_and_scheduled_coupons(): void
    {
        foreach ([Coupon::factory()->expired(), Coupon::factory()->disabled(), Coupon::factory()->scheduled()] as $factory) {
            $c = $factory->create();
            $this->assertRejected('unavailable', fn() => $this->service()->consume($c));
            $this->assertSame(0, $c->fresh()->times_used);
        }
    }

    public function test_consume_without_a_limit_counts_every_use(): void
    {
        $c = Coupon::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->service()->consume($c);
        }

        $this->assertSame(5, $c->fresh()->times_used);
    }

    public function test_release_gives_back_one_use_and_never_goes_negative(): void
    {
        $c = $this->used(Coupon::factory()->limit(3)->create(), 1);

        $this->service()->release($c);
        $this->assertSame(0, $c->fresh()->times_used);

        $this->service()->release($c);
        $this->assertSame(0, $c->fresh()->times_used);
    }

    public function test_exhausted_coupon_becomes_usable_again_after_release(): void
    {
        $c = Coupon::factory()->limit(1)->create();

        $this->service()->consume($c);
        $this->assertSame('exhausted', $c->fresh()->status());

        $this->service()->release($c);
        $this->assertSame('active', $c->fresh()->status());
    }

    public function test_times_used_is_not_mass_assignable(): void
    {
        $c = Coupon::create(['code' => 'MASS-1', 'discount_type' => 'fixed', 'discount_value' => 5, 'times_used' => 50]);

        $this->assertSame(0, $c->fresh()->times_used);
    }

    public function test_database_enforces_unique_codes(): void
    {
        Coupon::factory()->create(['code' => 'ONLY-1']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Coupon::factory()->create(['code' => 'ONLY-1']);
    }
}
