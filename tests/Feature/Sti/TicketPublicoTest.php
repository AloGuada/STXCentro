<?php

use App\Models\User;

describe('portal publico de tickets', function () {
    test('el enlace del sidebar apunta a una ruta registrada', function () {
        expect(route('sti.reportes.tickets.index', absolute: false))
            ->toBe('/sti/reportes/tickets');
    });

    test('el listado responde sin login', function () {
        $this->get('/sti/reportes/tickets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('sti/tickets-pendientes')
                ->has('tickets.data')
            );
    });

    test('el listado responde tambien con sesion iniciada', function () {
        $this->actingAs(User::factory()->create())
            ->get('/sti/reportes/tickets')
            ->assertOk();
    });
});
