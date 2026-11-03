<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'quiz_id'        => Quiz::factory(),
            'attempt_number' => 1,
            'status'         => 'submitted',
            'is_open'        => null,
            'score'          => 80,
            'points_earned'  => 8,
            'points_total'   => 10,
            'pass_mark'      => 50,
            'is_passed'      => true,
            'started_at'     => now()->subMinutes(30),
            'submitted_at'   => now()->subMinutes(20),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => 'in_progress', 'is_open' => true, 'score' => 0, 'points_earned' => 0,
            'is_passed' => false, 'submitted_at' => null, 'started_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['score' => 30, 'points_earned' => 3, 'is_passed' => false]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function number(int $number): static
    {
        return $this->state(fn () => ['attempt_number' => $number]);
    }
}
