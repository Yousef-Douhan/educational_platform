<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAnswerFactory extends Factory
{
    protected $model = QuizAnswer::class;

    public function definition(): array
    {
        return [
            'attempt_id'     => QuizAttempt::factory(),
            'question_id'    => Question::factory(),
            'option_id'      => null,
            'answer_text'    => null,
            'is_correct'     => false,
            'points_awarded' => 0,
        ];
    }
}
