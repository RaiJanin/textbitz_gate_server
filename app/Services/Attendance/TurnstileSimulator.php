<?php

namespace App\Services\Attendance;

use App\Models\Gate;
use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates realistic turnstile activity. `backfill()` writes weeks of history
 * (silently, via RecordTap::backfill); `liveTap()` fires one tap through the
 * full pipeline (broadcast + push) as if a card was just swiped.
 */
class TurnstileSimulator
{
    public function __construct(private RecordTap $recordTap) {}

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{student: string, on_time: int, late: int, absent: int, taps: int}>
     */
    public function backfill(Collection $students, Gate $gate, int $days, SimulationProfile $profile): Collection
    {
        $school = $gate->school;
        $today = Carbon::today($school->timezone);
        $odds = $profile->odds();

        return $students->map(function (Student $student) use ($gate, $today, $days, $odds, $school) {
            $counts = ['on_time' => 0, 'late' => 0, 'absent' => 0, 'taps' => 0];

            for ($day = $today->copy()->subDays($days); $day->lte($today); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                $roll = $this->pick($odds);
                $isToday = $day->isSameDay($today);

                if ($roll === 'absent') {
                    $counts['absent']++;

                    continue;
                }

                $arriveAt = $roll === 'late'
                    ? $this->timeAfter($day, $school->attendance_cutoff_time, 1, 55)
                    : $this->timeBefore($day, $school->attendance_cutoff_time, 5, 40);

                $this->recordTap->backfill($student, $gate, $arriveAt, forceDirection: TapEvent::DIRECTION_IN);
                $counts[$roll === 'late' ? 'late' : 'on_time']++;
                $counts['taps']++;

                // Everyone leaves on past days; today's cohort is still at school.
                if (! $isToday) {
                    $leaveAt = $day->copy()->setTime(15, mt_rand(0, 45));
                    $this->recordTap->backfill($student, $gate, $leaveAt, forceDirection: TapEvent::DIRECTION_OUT);
                    $counts['taps']++;
                }
            }

            return [
                'student' => $student->full_name,
                ...$counts,
            ];
        });
    }

    /**
     * One tap through the real pipeline (broadcast + push).
     *
     * @param  'in'|'out'|null  $direction  null lets the resolver toggle from the last tap
     * @param  'now'|'on-time'|'late'  $timing  shifts the timestamp relative to today's cutoff
     */
    public function liveTap(Student $student, Gate $gate, ?string $direction = null, string $timing = 'now', ?Carbon $at = null): TapEvent
    {
        $school = $student->school;
        $day = $school->localNow();

        $at ??= match ($timing) {
            'on-time' => $this->timeBefore($day, $school->attendance_cutoff_time, 5, 40),
            'late' => $this->timeAfter($day, $school->attendance_cutoff_time, 1, 40),
            default => Carbon::now(),
        };

        return $this->recordTap->record($student, $gate, $at, forceDirection: $direction, source: 'simulator');
    }

    /**
     * @param  array{on_time: float, late: float, absent: float}  $odds
     * @return 'on_time'|'late'|'absent'
     */
    private function pick(array $odds): string
    {
        $roll = mt_rand(0, 9999) / 10000;

        if ($roll < $odds['absent']) {
            return 'absent';
        }

        if ($roll < $odds['absent'] + $odds['late']) {
            return 'late';
        }

        return 'on_time';
    }

    private function timeBefore(Carbon $day, string $cutoff, int $minMinutes, int $maxMinutes): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $cutoff));

        return $day->copy()->setTime($h, $m)->subMinutes(mt_rand($minMinutes, $maxMinutes));
    }

    private function timeAfter(Carbon $day, string $cutoff, int $minMinutes, int $maxMinutes): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $cutoff));

        return $day->copy()->setTime($h, $m)->addMinutes(mt_rand($minMinutes, $maxMinutes));
    }
}
