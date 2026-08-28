<?php

namespace Database\Factories;

use App\Models\Gate;
use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TapEvent>
 */
class TapEventFactory extends Factory
{
    protected $model = TapEvent::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'gate_id' => Gate::factory(),
            'direction' => TapEvent::DIRECTION_IN,
            'tapped_at' => now(),
            'is_late' => false,
            'source' => 'test',
            'synced_at' => now(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn () => ['direction' => TapEvent::DIRECTION_OUT]);
    }

    public function late(): static
    {
        return $this->state(fn () => ['is_late' => true]);
    }
}
