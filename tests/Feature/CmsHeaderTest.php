<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_requires_authentication(): void
    {
        $this->getJson(route('cms.admin.my-notifications.latest'))->assertUnauthorized();
    }

    public function test_latest_returns_only_own_notifications_and_unread_count(): void
    {
        $me    = User::factory()->create();
        $other = User::factory()->create();

        foreach ([$me, $other] as $u) {
            $u->notifications()->create([
                'id'   => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\PlatformNotification',
                'data' => ['kind' => 'unknown', 'params' => []],
            ]);
        }

        $res = $this->actingAs($me)->getJson(route('cms.admin.my-notifications.latest'));

        $res->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('unread', 1);
    }
}
