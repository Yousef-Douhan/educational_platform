<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'course_id'        => Course::factory()->published(),
            'template_id'      => null,
            'certificate_code' => fn() => Certificate::generateCode(),
            'student_name'     => fn(array $a) => User::find($a['user_id'])->name,
            'course_title_ar'  => fn(array $a) => Course::find($a['course_id'])->title_ar,
            'course_title_en'  => fn(array $a) => Course::find($a['course_id'])->title_en,
            'issued_at'        => now(),
        ];
    }

    public function revoked(string $reason = 'Issued by mistake'): static
    {
        return $this->state(fn() => ['revoked_at' => now(), 'revoked_reason' => $reason]);
    }
}
