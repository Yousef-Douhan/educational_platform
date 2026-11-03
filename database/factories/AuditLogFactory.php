<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_name'     => fake()->name(),
            'user_email'    => fake()->unique()->safeEmail(),
            'action'        => 'course.updated',
            'subject_type'  => 'course',
            'event'         => 'updated',
            'subject_id'    => fake()->numberBetween(1, 500),
            'subject_label' => 'Subject ' . fake()->unique()->numerify('####'),
            'ip_address'    => '203.0.113.' . fake()->numberBetween(1, 250),
            'old_values'    => ['title_en' => 'Old'],
            'new_values'    => ['title_en' => 'New'],
        ];
    }

    public function of(string $type, string $event): static
    {
        return $this->state(['action' => "{$type}.{$event}", 'subject_type' => $type, 'event' => $event]);
    }
}
