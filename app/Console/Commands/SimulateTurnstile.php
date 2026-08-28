<?php

namespace App\Console\Commands;

use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\SimulationProfile;
use App\Services\Attendance\TurnstileSimulator;
use Illuminate\Console\Command;

class SimulateTurnstile extends Command
{
    protected $signature = 'gate:simulate
        {--school= : School id (default: first school)}
        {--gate= : Gate id to tap at (default: first gate in the school)}
        {--student=* : Limit to these student ids (default: every student in the school)}
        {--days=14 : How many days of history to build (weekdays only)}
        {--scenario=mixed : mixed | on-time | late | absent | perfect}
        {--fresh : Delete existing tap events for the targeted students first}
        {--seed=2026 : RNG seed, for reproducible runs}';

    protected $description = 'Back-fill weeks of simulated turnstile taps (on-time / late / absent) for one or more students';

    public function handle(TurnstileSimulator $simulator): int
    {
        $school = $this->option('school')
            ? School::findOrFail($this->option('school'))
            : School::firstOrFail();

        $gate = $this->option('gate')
            ? $school->gates()->findOrFail($this->option('gate'))
            : $school->gates()->firstOrFail();

        $students = $school->students()
            ->when($this->option('student'), fn ($q) => $q->whereIn('id', $this->option('student')))
            ->get();

        if ($students->isEmpty()) {
            $this->error('No students to simulate for.');

            return self::FAILURE;
        }

        $profile = SimulationProfile::tryFrom($this->option('scenario'));

        if (! $profile) {
            $this->error("Unknown scenario [{$this->option('scenario')}]. Use: mixed, on-time, late, absent, perfect.");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $deleted = \App\Models\TapEvent::whereIn('student_id', $students->pluck('id'))->delete();
            $this->warn("Cleared {$deleted} existing tap event(s).");
        }

        mt_srand((int) $this->option('seed'));

        $summary = $simulator->backfill(
            students: $students,
            gate: $gate,
            days: (int) $this->option('days'),
            profile: $profile,
        );

        $this->table(
            ['Student', 'On-time', 'Late', 'Absent', 'Taps'],
            $summary->map(fn ($row) => [
                $row['student'],
                $row['on_time'],
                $row['late'],
                $row['absent'],
                $row['taps'],
            ])->all(),
        );

        $this->info("Scenario [{$profile->value}] over the last {$this->option('days')} weekday(s) at {$gate->name}, {$school->name}.");
        $this->line('History, calendars and the alerts feed are all derived from these taps — open /simulator to see it.');

        return self::SUCCESS;
    }
}
