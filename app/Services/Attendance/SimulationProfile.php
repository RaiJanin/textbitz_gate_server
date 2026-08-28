<?php

namespace App\Services\Attendance;

/**
 * Day-by-day behaviour profiles for the turnstile simulator. Each profile maps
 * to per-day odds of an on-time arrival, a late arrival, or an absence.
 */
enum SimulationProfile: string
{
    case Mixed = 'mixed';
    case OnTime = 'on-time';
    case Late = 'late';
    case Absent = 'absent';
    case Perfect = 'perfect';

    /**
     * @return array{on_time: float, late: float, absent: float}
     */
    public function odds(): array
    {
        return match ($this) {
            self::Mixed => ['on_time' => 0.72, 'late' => 0.18, 'absent' => 0.10],
            self::OnTime => ['on_time' => 0.90, 'late' => 0.10, 'absent' => 0.0],
            self::Late => ['on_time' => 0.10, 'late' => 0.90, 'absent' => 0.0],
            self::Absent => ['on_time' => 0.0, 'late' => 0.0, 'absent' => 1.0],
            self::Perfect => ['on_time' => 1.0, 'late' => 0.0, 'absent' => 0.0],
        };
    }
}
