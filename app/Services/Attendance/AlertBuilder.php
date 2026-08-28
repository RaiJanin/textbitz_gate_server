<?php

namespace App\Services\Attendance;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Builds the "Alerts" feed for a student: late arrivals, derived absence
 * flags, and a rolling weekly on-time summary.
 */
class AlertBuilder
{
    public function __construct(private DayRecordBuilder $days) {}

    /**
     * @return array<int, array{type: string, date: string, title: string, body: string}>
     */
    public function forStudent(Student $student, int $days = 30): array
    {
        $tz = $student->school->timezone;
        $to = Carbon::now($tz)->startOfDay();
        $from = $to->copy()->subDays($days);

        $records = $this->days->forRange($student, $from, $to);
        $first = str($student->full_name)->before(' ')->toString() ?: $student->full_name;

        $alerts = [];

        foreach ($records as $record) {
            if ($record['state'] === DayRecordBuilder::STATE_LATE) {
                $alerts[] = [
                    'type' => 'late',
                    'date' => $record['date'],
                    'title' => 'Late arrival',
                    'body' => "{$first} arrived at {$record['first_in']} on ".Carbon::parse($record['date'])->format('M j').'.',
                ];
            }

            if ($record['state'] === DayRecordBuilder::STATE_ABSENT) {
                $alerts[] = [
                    'type' => 'absent',
                    'date' => $record['date'],
                    'title' => 'Marked absent',
                    'body' => "{$first} had no taps on ".Carbon::parse($record['date'])->format('M j').'. Contact the school if this looks wrong.',
                ];
            }
        }

        foreach ($this->weeklySummaries($records, $first) as $summary) {
            $alerts[] = $summary;
        }

        usort($alerts, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $alerts;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{date: string, state: string}>  $records
     * @return array<int, array{type: string, date: string, title: string, body: string}>
     */
    private function weeklySummaries($records, string $first): array
    {
        $byWeek = $records->groupBy(fn ($r) => Carbon::parse($r['date'])->startOfWeek()->toDateString());
        $summaries = [];

        foreach ($byWeek as $weekStart => $weekRecords) {
            $school = $weekRecords->whereIn('state', [
                DayRecordBuilder::STATE_ON_TIME,
                DayRecordBuilder::STATE_LATE,
                DayRecordBuilder::STATE_ABSENT,
            ]);

            if ($school->isEmpty()) {
                continue;
            }

            $onTime = $school->where('state', DayRecordBuilder::STATE_ON_TIME)->count();

            $summaries[] = [
                'type' => 'weekly_summary',
                'date' => $weekStart,
                'title' => 'Weekly summary',
                'body' => "{$first} was on time {$onTime} of {$school->count()} school days the week of ".Carbon::parse($weekStart)->format('M j').'.',
            ];
        }

        return $summaries;
    }
}
