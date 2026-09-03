<?php

namespace Database\Factories;

use App\Models\AdminUser;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '+639'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'school_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /** Scope this admin to one school (not a super-admin). */
    public function forSchool(School|int|null $school = null): static
    {
        return $this->state(fn () => [
            'school_id' => $school instanceof School ? $school->id : ($school ?? School::factory()),
        ]);
    }
}
