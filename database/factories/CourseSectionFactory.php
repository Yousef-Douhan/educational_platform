<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    public function definition(): array
    {
        return [
            'course_id'  => Course::factory(),
            'title_ar'   => 'قسم ' . fake()->unique()->numerify('####'),
            'title_en'   => 'Section ' . fake()->unique()->numerify('####'),
            'sort_order' => 0,
        ];
    }
}
