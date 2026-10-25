<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'course_id'   => Course::factory()->published(),
            'status'      => 'active',
            'enrolled_at' => now(),
            'expires_at'  => null,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn() => ['status' => $status]);
    }

    public function expiresAt($date): static
    {
        return $this->state(fn() => ['expires_at' => $date]);
    }
}
