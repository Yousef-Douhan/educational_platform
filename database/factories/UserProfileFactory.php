<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'bio'          => fake()->paragraph(),
            'avatar'       => null,
            'phone'        => fake()->numerify('+970 59# ### ###'),
            'website'      => fake()->url(),
            'social_links' => 'https://x.com/' . fake()->userName(),
        ];
    }
}
