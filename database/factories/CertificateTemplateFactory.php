<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'name'        => 'Template ' . fake()->unique()->numerify('####'),
            'design_path' => 'certificate-templates/' . Str::random(20) . '.png',
            'is_default'  => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn() => ['is_default' => true]);
    }
}
