<?php

use Inertia\Testing\AssertableInertia;

it('muestra el prototipo del tablero simplificado sin autenticacion', function () {
    $this->get('/portal/prueba')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('portal/prueba/tablero'));
});
