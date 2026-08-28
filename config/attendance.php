<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tap event retention
    |--------------------------------------------------------------------------
    |
    | How many days of tap_events to keep before the attendance:purge-taps
    | command deletes them. Children's movement data is sensitive — keep the
    | window as short as the school's needs allow (default: one school year
    | plus a small buffer).
    |
    */

    'retention_days' => (int) env('ATTENDANCE_RETENTION_DAYS', 400),

    /*
    |--------------------------------------------------------------------------
    | Gate health
    |--------------------------------------------------------------------------
    |
    | A gate is flipped to "offline" when it has not sent a tap or heartbeat
    | within this many minutes.
    |
    */

    'gate_offline_after_minutes' => (int) env('GATE_OFFLINE_AFTER_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Absence check
    |--------------------------------------------------------------------------
    |
    | Local (server) time at which the daily attendance:flag-absent job runs.
    | Any enrolled student with zero taps for the school day is flagged absent.
    |
    */

    'absent_check_time' => env('ATTENDANCE_ABSENT_CHECK_TIME', '09:00'),

];
