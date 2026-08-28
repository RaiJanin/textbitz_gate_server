<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['alive' => true]));

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);

/*
| Turnstile ingest — authenticated by the school's ingest_token, not a user session.
*/
Route::middleware('ingest.token')->group(function () {
    Route::post('/ingest/tap', [IngestController::class, 'tap']);
});

/*
| Mobile app — guardian / student accounts (Sanctum tokens).
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [MeController::class, 'show']);

    Route::get('/students/{student}/status', [StudentController::class, 'status'])->can('view', 'student');
    Route::get('/students/{student}/history', [StudentController::class, 'history'])->can('view', 'student');
    Route::get('/students/{student}/alerts', [StudentController::class, 'alerts'])->can('view', 'student');

    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

    Route::post('/link/request', [LinkController::class, 'store']);

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
