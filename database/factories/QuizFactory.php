<?php
namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'lesson_id'          => Lesson::factory()->state(['content_type' => 'quiz', 'content_ar' => null]),
            'title_ar'           => 'اختبار ' . fake()->unique()->numerify('####'),
            'title_en'           => 'Quiz ' . fake()->unique()->numerify('####'),
            'pass_mark'          => 50,
            'time_limit_minutes' => 0,
            'attempts_allowed'   => 3,
        ];
    }
}
