<?php

use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('asignacion activo pdf', function () {
    test('pdf can be generated', function () {
        $equipo = Equipo::factory()->create();
        $asignacion = AsignacionActivo::factory()->create(['equipo_id' => $equipo->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.asignacion-activos.pdf', $asignacion));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    test('pdf includes equipo items', function () {
        $equipo = Equipo::factory()->create();
        $item = Item::factory()->create(['principal' => true]);
        Grupo::factory()->create(['equipo_id' => $equipo->id, 'item_id' => $item->id]);

        $accesorio = Item::factory()->create(['accesorio' => true]);
        Grupo::factory()->create(['equipo_id' => $equipo->id, 'item_id' => $accesorio->id]);

        $asignacion = AsignacionActivo::factory()->create(['equipo_id' => $equipo->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.asignacion-activos.pdf', $asignacion));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    test('pdf works without items', function () {
        $asignacion = AsignacionActivo::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.asignacion-activos.pdf', $asignacion));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });
});
