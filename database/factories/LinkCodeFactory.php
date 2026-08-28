<?php

namespace Database\Factories;

use App\Models\LinkCode;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LinkCode>
 */
class LinkCodeFactory extends Factory
{
    protected $model = LinkCode::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'code' => strtoupper(Str::random(8)),
            'default_relationship' => 'Guardian',
            'expires_at' => now()->addDays(14),
            'consumed_at' => null,
            'consumed_by_guardian_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['consumed_at' => now()]);
    }
}
