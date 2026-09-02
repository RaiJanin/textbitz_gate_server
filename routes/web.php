<?php

use App\Http\Controllers\Admin\LinkCodeSlipController;
use App\Http\Controllers\SimulatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Printable guardian link-code slips, opened from the Filament admin panel.
| Auth + admin check live in the controller.
*/
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('link-codes/slips', [LinkCodeSlipController::class, 'batch'])->name('link-codes.slips');
    Route::get('link-codes/{linkCode}/slip', [LinkCodeSlipController::class, 'show'])->name('link-codes.slip');
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
