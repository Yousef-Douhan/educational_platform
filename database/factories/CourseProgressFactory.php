<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseProgressFactory extends Factory
{
    protected $model = CourseProgress::class;

    public function definition(): array
    {
        return [
            'user_id'                 => User::factory(),
            'course_id'               => Course::factory()->published(),
            'completed_lessons_count' => 1,
            'total_lessons_count'     => 2,
            'progress_percentage'     => 50,
            'is_finished'             => false,
            'finished_at'             => null,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'completed_lessons_count' => 2, 'total_lessons_count' => 2,
            'progress_percentage' => 100, 'is_finished' => true, 'finished_at' => now(),
        ]);
    }

    public function percent(float $percent): static
    {
        return $this->state(fn () => ['progress_percentage' => $percent]);
    }
}
