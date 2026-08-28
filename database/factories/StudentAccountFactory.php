<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAccount>
 */
class StudentAccountFactory extends Factory
{
    protected $model = StudentAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_id' => Student::factory(),
        ];
    }
}
