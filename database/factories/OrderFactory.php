<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number'    => fn () => Order::generateNumber(),
            'user_id'         => User::factory(),
            'subtotal'        => 100,
            'discount_amount' => 0,
            'total_amount'    => 100,
            'currency'        => 'USD',
            'status'          => 'pending',
        ];
    }

    public function paid(string $gateway = 'manual', ?string $reference = null): static
    {
        return $this->state(fn () => [
            'status' => 'paid', 'gateway' => $gateway, 'transaction_reference' => $reference, 'paid_at' => now(),
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function createdAgo(int $hours): static
    {
        return $this->state(fn () => ['created_at' => now()->subHours($hours), 'updated_at' => now()->subHours($hours)]);
    }
}
