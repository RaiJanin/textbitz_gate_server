<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:sweep-gates')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('attendance:flag-absent')
    ->dailyAt(config('attendance.absent_check_time'))
    ->withoutOverlapping();

Schedule::command('attendance:purge-taps')
    ->dailyAt('01:30')
    ->withoutOverlapping();
