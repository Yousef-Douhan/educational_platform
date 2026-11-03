<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UserNotification> */
class UserNotificationFactory extends Factory
{
    protected $model = UserNotification::class;

    public function definition(): array
    {
        return [
            'id'              => (string) Str::uuid(),
            'type'            => 'enrollment.activated',
            'notifiable_type' => User::class,
            'notifiable_id'   => User::factory(),
            'data'            => [
                'kind'   => 'enrollment.activated',
                'params' => ['course_title_ar' => 'دورة تجريبية', 'course_title_en' => 'Sample course'],
            ],
            'read_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['notifiable_type' => User::class, 'notifiable_id' => $user->id]);
    }

    public function kind(string $kind, array $params = []): static
    {
        return $this->state(['type' => $kind, 'data' => ['kind' => $kind, 'params' => $params]]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function at($when): static
    {
        return $this->state(['created_at' => $when, 'updated_at' => $when]);
    }
}
