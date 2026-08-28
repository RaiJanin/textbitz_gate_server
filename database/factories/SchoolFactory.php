<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' National High School',
            'timezone' => 'Asia/Manila',
            'attendance_cutoff_time' => '07:45:00',
            'ingest_token' => Str::random(48),
            'contact_phone' => '+63288888888',
            'contact_email' => 'office@school.example',
        ];
    }
}
