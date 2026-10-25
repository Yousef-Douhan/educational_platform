<?php

namespace Database\Factories;

use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'section_id'       => CourseSection::factory(),
            'title_ar'         => 'درس ' . fake()->unique()->numerify('####'),
            'title_en'         => 'Lesson ' . fake()->unique()->numerify('####'),
            'content_type'     => 'article',
            'content_ar'       => 'محتوى الدرس',
            'duration_minutes' => 0,
            'is_free_preview'  => false,
            'is_published'     => false,
            'sort_order'       => 0,
        ];
    }

    public function video(): static
    {
        return $this->state(fn() => [
            'content_type'   => 'video',
            'video_provider' => 'youtube',
            'video_url'      => 'https://www.youtube.com/watch?v=abc123',
            'content_ar'     => null,
        ]);
    }
}
