<?php

use App\Models\User;

it('registers a new account with a guardian profile and default preferences', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Nena Santos',
        'phone_number' => '+639170001111',
        'password' => 'password123',
        'device_name' => 'pixel-7',
    ])->assertCreated();

    $user = User::where('phone_number', '+639170001111')->firstOrFail();

    expect($user->guardian)->not->toBeNull()
        ->and($user->roles())->toBe(['guardian'])
        ->and($user->preferencesFor('guardian')->arrival)->toBeTrue();

    expect($response->json('token'))->toBeString();
});

it('logs in with valid credentials', function () {
    User::factory()->create([
        'phone_number' => '+639170002222',
        'password' => bcrypt('password123'),
    ]);

    $this->postJson('/api/login', [
        'phone_number' => '+639170002222',
        'password' => 'password123',
        'device_name' => 'pixel-7',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'phone_number' => '+639170003333',
        'password' => bcrypt('password123'),
    ]);

    $this->postJson('/api/login', [
        'phone_number' => '+639170003333',
        'password' => 'wrong',
        'device_name' => 'pixel-7',
    ])->assertStatus(422);
});
