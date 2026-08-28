<?php

namespace App\Console\Commands;

use App\Events\StudentMarkedAbsent;
use App\Jobs\SendPushNotification;
use App\Models\NotificationPreference;
use App\Models\School;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * End-of-day derivation of "absent": any enrolled student with zero taps for
 * today (in their school's timezone) is flagged. Absence is never stored as a
 * tap — this just emits the alert/push.
 */
class FlagAbsentStudents extends Command
{
    protected $signature = 'attendance:flag-absent {--date= : Override the school-day to check (Y-m-d)}';

    protected $description = 'Flag enrolled students with no taps today as absent';

    public function handle(): int
    {
        $flagged = 0;

        School::query()->each(function (School $school) use (&$flagged) {
            $day = $this->option('date')
                ? Carbon::parse($this->option('date'), $school->timezone)
                : $school->localNow();

            if ($day->isWeekend()) {
                return;
            }

            $start = $day->copy()->startOfDay()->utc();
            $end = $day->copy()->endOfDay()->utc();

            $school->students()
                ->whereDoesntHave('tapEvents', fn ($query) => $query->whereBetween('tapped_at', [$start, $end]))
                ->with(['guardians.user', 'studentAccount.user'])
                ->each(function (Student $student) use ($day, &$flagged) {
                    StudentMarkedAbsent::dispatch($student, $day->copy());

                    $recipients = $student->guardians
                        ->map(fn ($g) => [$g->user, NotificationPreference::ROLE_GUARDIAN])
                        ->push([$student->studentAccount?->user, NotificationPreference::ROLE_STUDENT])
                        ->filter(fn ($pair) => $pair[0] !== null);

                    $first = str($student->full_name)->before(' ')->toString() ?: $student->full_name;

                    foreach ($recipients as [$user, $role]) {
                        if (! $user->preferencesFor($role)->allows('late_alert')) {
                            continue;
                        }

                        SendPushNotification::dispatch(
                            $user->id,
                            'Marked absent',
                            "{$first} has no attendance taps today. Contact the school if this doesn't look right.",
                            ['type' => 'absent', 'student_id' => (string) $student->id, 'date' => $day->toDateString()],
                        );
                    }

                    $flagged++;
                });
        });

        $this->info("Flagged {$flagged} student(s) absent.");

        return self::SUCCESS;
    }
}
