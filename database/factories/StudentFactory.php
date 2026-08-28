<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'full_name' => fake()->name(),
            'grade' => (string) fake()->numberBetween(7, 12),
            'section' => fake()->randomElement(['Rizal', 'Bonifacio', 'Mabini', 'Luna']),
            'rfid_uid' => strtoupper(Str::random(10)),
            'avatar_path' => null,
        ];
    }
}
