<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns default preferences for a guardian account', function () {
    $user = User::factory()->create(); // UserObserver adds the guardian profile + prefs

    Sanctum::actingAs($user);

    $this->getJson('/api/notification-preferences')
        ->assertOk()
        ->assertJsonPath('preferences.0.role', 'guardian')
        ->assertJsonPath('preferences.0.arrival', true);
});

it('updates a toggle for a role the account holds', function () {
    $user = User::factory()->create(); // UserObserver adds the guardian profile + prefs

    Sanctum::actingAs($user);

    $this->putJson('/api/notification-preferences', ['role' => 'guardian', 'weekly_summary' => false])
        ->assertOk()
        ->assertJsonPath('preference.weekly_summary', false);

    expect($user->preferencesFor('guardian')->weekly_summary)->toBeFalse();
});

it('rejects updating a role the account does not hold', function () {
    $user = User::factory()->create(); // UserObserver adds the guardian profile + prefs

    Sanctum::actingAs($user);

    $this->putJson('/api/notification-preferences', ['role' => 'student', 'arrival' => false])
        ->assertForbidden();
});
