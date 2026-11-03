<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id'  => Order::factory(),
            'course_id' => Course::factory()->published()->paid(),
            'price'     => 100,
            'title_ar'  => 'دورة تجريبية',
            'title_en'  => 'Sample course',
        ];
    }
}
