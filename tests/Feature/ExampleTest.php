<?php

use App\Models\Usuario;

test('returns a successful response', function () {
    $user = Usuario::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
});
