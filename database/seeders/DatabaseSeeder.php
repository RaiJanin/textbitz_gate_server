<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Gate;
use App\Models\LinkCode;
use App\Models\NotificationPreference;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\TapEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $ingestToken = Str::random(48);

        $school = School::create([
            'name' => 'Sampaguita National High School',
            'timezone' => 'Asia/Manila',
            'attendance_cutoff_time' => '07:45:00',
            'ingest_token' => $ingestToken,
            'contact_phone' => '+63288887777',
            'contact_email' => 'office@sampaguita.example',
        ]);

        $mainGate = Gate::create(['school_id' => $school->id, 'name' => 'Main Gate']);
        Gate::create(['school_id' => $school->id, 'name' => 'Side Gate']);

        $students = collect([
            ['full_name' => 'Diana Reyes', 'grade' => '9', 'section' => 'Rizal', 'rfid_uid' => 'RFID-DIANA-01'],
            ['full_name' => 'Marco Reyes', 'grade' => '11', 'section' => 'Mabini', 'rfid_uid' => 'RFID-MARCO-01'],
            ['full_name' => 'Sofia Cruz', 'grade' => '8', 'section' => 'Luna', 'rfid_uid' => 'RFID-SOFIA-01'],
            ['full_name' => 'Liam Santos', 'grade' => '10', 'section' => 'Bonifacio', 'rfid_uid' => 'RFID-LIAM-01'],
        ])->map(fn (array $attributes) => Student::create([...$attributes, 'school_id' => $school->id]));

        $this->seedTapHistory($students[0], $mainGate, $school);
        $this->seedTapHistory($students[1], $mainGate, $school, Carbon::today($school->timezone)->subDays(7));

        // A pending school-issued link code for Sofia Cruz.
        $linkCode = LinkCode::create([
            'school_id' => $school->id,
            'student_id' => $students[2]->id,
            'code' => 'GATE-SOFIA',
            'default_relationship' => 'Guardian',
            'expires_at' => now()->addDays(30),
        ]);

        // Known-password admin / guardian / student logins are for local + staging
        // only. On production, create admins with `php artisan make:filament-user`.
        if (! app()->isProduction()) {
            $this->seedDemoAccounts($school, $students);
        }

        $this->command->info('── TextBitz Gate seed ───────────────────────────────');
        $this->command->info("School ingest token : {$ingestToken}");
        $this->command->info("Main gate id        : {$mainGate->id}");
        $this->command->info("Sample RFID UID     : {$students[0]->rfid_uid} (Diana Reyes)");
        $this->command->info("Pending link code   : {$linkCode->code} (Sofia Cruz)");
        $this->command->info('─────────────────────────────────────────────────────');
    }

    /**
     * Demo admin-panel and client-app logins (all password "password"). Skipped
     * when APP_ENV=production.
     *
     * @param  Collection<int, Student>  $students
     */
    private function seedDemoAccounts(School $school, Collection $students): void
    {
        AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@textbitzgate.test',
            'phone_number' => '+639170000900',
            'password' => Hash::make('password'),
        ]);

        AdminUser::create([
            'name' => 'Sampaguita Office',
            'email' => 'office-admin@textbitzgate.test',
            'phone_number' => '+639170000901',
            'password' => Hash::make('password'),
            'school_id' => $school->id,
        ]);

        // Guardian account linked to the two "Reyes" students. The UserObserver
        // creates the matching Guardian profile + guardian preferences.
        $guardianUser = User::create([
            'name' => 'Elena Reyes',
            'email' => 'parent@textbitzgate.test',
            'phone_number' => '+639171234567',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $guardianUser->guardian->update(['role' => 'Parent']);
        $guardianUser->guardian->students()->attach([
            $students[0]->id => ['relationship' => 'Parent'],
            $students[1]->id => ['relationship' => 'Parent'],
        ]);

        // Student self-login for Marco (also has a guardian profile like every
        // client account; the StudentAccount adds the student role).
        $studentUser = User::create([
            'name' => 'Marco Reyes',
            'email' => 'student@textbitzgate.test',
            'phone_number' => '+639170000002',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $students[1]->id]);
        $studentUser->preferencesFor(NotificationPreference::ROLE_STUDENT);
    }

    /**
     * Backfill ~3 weeks of weekday taps for one student: a morning arrival
     * (some after the cutoff) and, on past days, an afternoon departure.
     * Routed through RecordTap so it matches real ingest exactly.
     */
    private function seedTapHistory(Student $student, Gate $gate, School $school, ?Carbon $absentDay = null): void
    {
        $recordTap = app(\App\Services\Attendance\RecordTap::class);
        $today = Carbon::today($school->timezone);

        for ($day = $today->copy()->subDays(21); $day->lte($today); $day->addDay()) {
            if (! $day->isWeekday() || ($absentDay && $day->isSameDay($absentDay))) {
                continue;
            }

            $arriveMinute = [30, 38, 41, 47, 52][$day->dayOfYear % 5];
            $recordTap->backfill($student, $gate, $day->copy()->setTime(7, $arriveMinute), forceDirection: TapEvent::DIRECTION_IN, source: 'seed');

            if ($day->lt($today)) {
                $recordTap->backfill($student, $gate, $day->copy()->setTime(15, [10, 22, 35][$day->dayOfYear % 3]), forceDirection: TapEvent::DIRECTION_OUT, source: 'seed');
            }
        }
    }
}
