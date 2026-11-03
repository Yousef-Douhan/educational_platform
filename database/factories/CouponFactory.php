<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'           => strtoupper(fake()->unique()->bothify('SAVE-????##')),
            'discount_type'  => 'percentage',
            'discount_value' => 10,
            'is_active'      => true,
        ];
    }

    public function fixed(float $value = 5): static
    {
        return $this->state(['discount_type' => 'fixed', 'discount_value' => $value, 'max_discount' => null]);
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(['discount_type' => 'percentage', 'discount_value' => $value]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDay()]);
    }

    public function disabled(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function limit(int $limit, int $used = 0): static
    {
        return $this->state(['usage_limit' => $limit, 'times_used' => $used]);
    }
}
