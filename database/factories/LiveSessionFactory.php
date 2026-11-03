<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(2, 30))->setTime(10, 0);

        return [
            'course_id'        => Course::factory()->published(),
            'title_ar'         => 'جلسة ' . fake()->unique()->numerify('####'),
            'title_en'         => 'Session ' . fake()->unique()->numerify('####'),
            'meeting_url'      => 'https://meet.example.com/' . fake()->unique()->bothify('????-####'),
            'scheduled_at'     => $start,
            'duration_minutes' => 60,
            'status'           => 'scheduled',
        ];
    }

    public function at(\DateTimeInterface $start, int $minutes = 60): static
    {
        return $this->state(['scheduled_at' => $start, 'duration_minutes' => $minutes]);
    }

    public function live(): static
    {
        return $this->state(['scheduled_at' => now()->subMinutes(10), 'duration_minutes' => 60]);
    }

    public function past(): static
    {
        return $this->state(['scheduled_at' => now()->subDays(2), 'duration_minutes' => 60]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'test']);
    }
}
