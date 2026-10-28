<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** مستخدم يجتاز كل فحوصات Gate والـ can: دون تعداد أسماء الصلاحيات في كل اختبار */
    protected function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        Gate::before(fn($u) => $u->is($user) ? true : null);
        $this->actingAs($user);

        return $user;
    }
}
