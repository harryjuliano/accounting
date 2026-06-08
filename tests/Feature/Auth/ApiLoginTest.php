<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('creates a bearer token for valid api login credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Login successful.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'admin@example.com')
        ->assertJsonStructure([
            'access_token',
        ]);

    expect(PersonalAccessToken::count())->toBe(1);
});

it('rejects invalid api login credentials', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
});
