<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $en = fake()->unique()->words(2, true);

        return [
            'name_ar'   => 'فئة ' . fake()->unique()->numerify('####'),
            'name_en'   => $en,
            'slug'      => Str::slug($en) . '-' . fake()->unique()->numerify('####'),
            'icon'      => 'fa-solid fa-book',
            'parent_id' => null,
        ];
    }
}
