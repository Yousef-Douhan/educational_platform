<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return ['user_id' => User::factory()];
    }

    /** يحرّك updated_at للخلف (مهجورة) */
    public function idleFor(int $days): static
    {
        return $this->state(fn () => ['updated_at' => now()->subDays($days)]);
    }
}
