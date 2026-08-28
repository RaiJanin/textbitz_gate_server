<?php

use App\Http\Controllers\SimulatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Turnstile simulator — dev tool, only mounted when APP_DEBUG is on.
*/
if (config('app.debug')) {
    Route::prefix('simulator')->name('simulator.')->group(function () {
        Route::get('/', [SimulatorController::class, 'index'])->name('index');
        Route::post('/tap', [SimulatorController::class, 'tap'])->name('tap');
        Route::post('/backfill', [SimulatorController::class, 'backfill'])->name('backfill');
        Route::post('/flag-absent', [SimulatorController::class, 'flagAbsent'])->name('flag-absent');
        Route::post('/reset', [SimulatorController::class, 'reset'])->name('reset');
    });
}
