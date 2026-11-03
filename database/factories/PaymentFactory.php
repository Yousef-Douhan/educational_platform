<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id'       => Order::factory(),
            'gateway'        => 'stripe',
            'payment_method' => 'card',
            'amount'         => 100,
            'currency'       => 'USD',
            'status'         => 'pending',
        ];
    }

    public function success(?string $reference = null): static
    {
        return $this->state(fn () => [
            'status' => 'success', 'succeeded' => true, 'paid_at' => now(),
            'transaction_reference' => $reference ?? 'pi_' . fake()->unique()->bothify('????######'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
