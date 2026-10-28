<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'quiz_id'       => Quiz::factory(),
            'question_text' => fake()->sentence() . '?',
            'question_type' => 'multiple_choice',
            'points'        => 1,
            'sort_order'    => 0,
        ];
    }
}
