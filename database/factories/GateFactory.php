<?php

namespace Database\Factories;

use App\Models\Gate;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gate>
 */
class GateFactory extends Factory
{
    protected $model = Gate::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->randomElement(['Main Gate', 'Side Gate', 'Rear Gate']),
            'status' => Gate::STATUS_OFFLINE,
            'last_seen_at' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'status' => Gate::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);
    }
}
