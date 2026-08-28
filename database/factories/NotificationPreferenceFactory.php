<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => NotificationPreference::ROLE_GUARDIAN,
            'arrival' => true,
            'departure' => true,
            'late_alert' => true,
            'weekly_summary' => true,
        ];
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => NotificationPreference::ROLE_STUDENT]);
    }
}
