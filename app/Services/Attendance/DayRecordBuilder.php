<?php

namespace App\Services\Attendance;

use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Collapses a student's raw tap stream into one record per school day, used by
 * both the "today status" and "month history" endpoints. "Absent" is derived
 * here, never stored.
 */
class DayRecordBuilder
{
    public const STATE_ON_TIME = 'on_time';
    public const STATE_LATE = 'late';
    public const STATE_ABSENT = 'absent';
    public const STATE_NONE = 'none';

    /**
     * @return Collection<int, array{date: string, first_in: ?string, last_out: ?string, state: string, taps: array<int, array{direction: string, at: string, gate: ?string, is_late: bool}>}>
     */
    public function forRange(Student $student, Carbon $from, Carbon $to): Collection
    {
        $tz = $student->school->timezone;

        $taps = $student->tapEvents()
            ->with('gate:id,name')
            ->whereBetween('tapped_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])
            ->orderBy('tapped_at')
            ->get()
            ->groupBy(fn (TapEvent $tap) => $tap->tapped_at->copy()->setTimezone($tz)->toDateString());

        $today = Carbon::now($tz)->startOfDay();
        $records = collect();

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            /** @var Collection<int, TapEvent> $dayTaps */
            $dayTaps = $taps->get($key, collect());

            $records->push($this->buildDay($day->copy(), $dayTaps, $tz, $today));
        }

        return $records;
    }

    public function forDay(Student $student, Carbon $day): array
    {
        return $this->forRange($student, $day->copy(), $day->copy())->first();
    }

    /**
     * @param  Collection<int, TapEvent>  $dayTaps
     * @return array{date: string, first_in: ?string, last_out: ?string, state: string, taps: array<int, array{direction: string, at: string, gate: ?string, is_late: bool}>}
     */
    private function buildDay(Carbon $day, Collection $dayTaps, string $tz, Carbon $today): array
    {
        $firstIn = $dayTaps->firstWhere('direction', TapEvent::DIRECTION_IN);
        $lastOut = $dayTaps->where('direction', TapEvent::DIRECTION_OUT)->last();

        $state = match (true) {
            $firstIn !== null && $firstIn->is_late => self::STATE_LATE,
            $firstIn !== null => self::STATE_ON_TIME,
            $this->isPastSchoolDay($day, $today) => self::STATE_ABSENT,
            default => self::STATE_NONE,
        };

        return [
            'date' => $day->toDateString(),
            'first_in' => $firstIn?->tapped_at->copy()->setTimezone($tz)->format('H:i'),
            'last_out' => $lastOut?->tapped_at->copy()->setTimezone($tz)->format('H:i'),
            'state' => $state,
            'taps' => $dayTaps->map(fn (TapEvent $tap) => [
                'direction' => $tap->direction,
                'at' => $tap->tapped_at->copy()->setTimezone($tz)->format('H:i'),
                'gate' => $tap->gate?->name,
                'is_late' => (bool) $tap->is_late,
            ])->values()->all(),
        ];
    }

    private function isPastSchoolDay(Carbon $day, Carbon $today): bool
    {
        return $day->lt($today) && $day->isWeekday();
    }
}
