<?php

namespace App\Services\Attendance;

use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Support\Carbon;

/**
 * Turns a raw turnstile signal (student + timestamp) into a resolved
 * direction (in/out) and lateness flag. This logic is server-authoritative
 * and must never be pushed to the client.
 */
class TapResolver
{
    /**
     * Direction is a toggle from the student's most recent tap on the same
     * school day. The first tap of the day is always an arrival ("in").
     */
    public function resolveDirection(Student $student, Carbon $tappedAt): string
    {
        $school = $student->school;
        $localDay = $tappedAt->copy()->setTimezone($school->timezone);

        $previous = $student->tapEvents()
            ->whereBetween('tapped_at', [
                $localDay->copy()->startOfDay()->utc(),
                $localDay->copy()->endOfDay()->utc(),
            ])
            ->orderByDesc('tapped_at')
            ->orderByDesc('id')
            ->first();

        if (! $previous) {
            return TapEvent::DIRECTION_IN;
        }

        return $previous->direction === TapEvent::DIRECTION_IN
            ? TapEvent::DIRECTION_OUT
            : TapEvent::DIRECTION_IN;
    }

    /**
     * A tap is "late" only when it is an arrival after the school's cutoff
     * time, evaluated in the school's own timezone.
     */
    public function isLate(School $school, string $direction, Carbon $tappedAt): bool
    {
        if ($direction !== TapEvent::DIRECTION_IN) {
            return false;
        }

        $local = $tappedAt->copy()->setTimezone($school->timezone);

        return $local->greaterThan($school->cutoffFor($local));
    }
}
