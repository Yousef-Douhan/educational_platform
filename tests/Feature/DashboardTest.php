<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $u;
    }

    public function test_dashboard_requires_staff(): void
    {
        $this->get(route('cms.admin.dashboard'))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('cms.admin.dashboard'))->assertRedirect(route('student.courses.mine'));
    }

    public function test_sections_follow_permissions(): void
    {
        $limited = $this->staff(['carts.view']);
        $this->actingAs($limited)->get(route('cms.admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Total revenue')
            ->assertDontSee('Recent orders');

        $full = $this->staff(['users.view', 'courses.view', 'enrollments.view', 'orders.view', 'receipts.view', 'live-sessions.view']);
        $this->withCookie('locale', 'en')->actingAs($full)->get(route('cms.admin.dashboard'))
            ->assertOk()
            ->assertSee('Total revenue')
            ->assertSee('Recent orders')
            ->assertSee('Top courses');
    }

    public function test_dashboard_shows_real_revenue_and_is_localized(): void
    {
        $full = $this->staff(['users.view', 'courses.view', 'enrollments.view', 'orders.view', 'receipts.view', 'live-sessions.view']);

        Order::forceCreate([
            'order_number' => 'ORD-TEST-1', 'user_id' => $full->id, 'subtotal' => 120, 'discount_amount' => 0,
            'total_amount' => 120, 'currency' => 'USD', 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->withCookie('locale', 'en')->actingAs($full)->get(route('cms.admin.dashboard'))
            ->assertOk()->assertSee('120.00')->assertSee('ORD-TEST-1');

        $this->withCookie('locale', 'ar')->actingAs($full)->get(route('cms.admin.dashboard'))
            ->assertOk()->assertSee('إجمالي الإيرادات');
    }
}
