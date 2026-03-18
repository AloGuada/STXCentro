<?php

use App\Models\User;

test('login returns token with valid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized();
});

test('authenticated user can get their info', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/user');

    $response->assertOk()
        ->assertJsonStructure(['id', 'name', 'email']);
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/user');

    $response->assertUnauthorized();
});

test('logout revokes token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('swapp')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $response->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
