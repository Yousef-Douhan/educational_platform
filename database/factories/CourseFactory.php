<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $en = fake()->unique()->words(3, true);

        return [
            'instructor_id' => User::factory(),
            'category_id'   => Category::factory(),
            'title_ar'      => 'دورة ' . fake()->unique()->numerify('####'),
            'title_en'      => $en,
            'slug'          => Str::slug($en) . '-' . fake()->unique()->numerify('####'),
            'price'         => 0,
            'level'         => 'beginner',
            'status'        => 'draft',
        ];
    }

    public function paid(float $price = 100): static
    {
        return $this->state(fn() => ['price' => $price]);
    }

    public function published(): static
    {
        return $this->state(fn() => ['status' => 'published']);
    }
}
