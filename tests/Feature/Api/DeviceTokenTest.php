<?php

use App\Models\DeviceToken;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers a device token for the authenticated account', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/device-tokens', ['token' => 'fcm-abc-123', 'platform' => 'android'])
        ->assertCreated()
        ->assertJsonPath('registered', true);

    expect(DeviceToken::where('user_id', $user->id)->where('token', 'fcm-abc-123')->exists())->toBeTrue();
});

it('is idempotent on the same token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/device-tokens', ['token' => 'fcm-dup']);
    $this->postJson('/api/device-tokens', ['token' => 'fcm-dup']);

    expect(DeviceToken::where('token', 'fcm-dup')->count())->toBe(1);
});

it('removes a device token', function () {
    $user = User::factory()->create();
    DeviceToken::factory()->for($user)->create(['token' => 'fcm-gone']);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/device-tokens', ['token' => 'fcm-gone'])->assertOk();

    expect(DeviceToken::where('token', 'fcm-gone')->exists())->toBeFalse();
});
