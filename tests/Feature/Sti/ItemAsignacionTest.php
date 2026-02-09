<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\Sti\Tecnico;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('item asignacion', function () {
    test('item can be assigned to equipo', function () {
        $item = Item::factory()->create(['estado' => 'disponible']);
        $equipo = Equipo::factory()->create();
        $tecnico = Tecnico::factory()->create(['activo' => true]);

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $equipo))
            ->post(route('admin.sti.items.asignar', $item), [
                'equipo_id' => $equipo->id,
                'tecnico_id' => $tecnico->id,
                'observaciones' => 'Instalacion de RAM',
            ]);

        $response->assertRedirect(route('admin.sti.equipos.edit', $equipo));

        // Grupo created
        $this->assertDatabaseHas('sti_grupos', [
            'equipo_id' => $equipo->id,
            'item_id' => $item->id,
        ]);

        // Estado changed
        $this->assertDatabaseHas('sti_items', [
            'id' => $item->id,
            'estado' => 'instalado',
        ]);

        // Historial created
        $this->assertDatabaseHas('sti_items_historial', [
            'item_id' => $item->id,
            'equipo_id' => $equipo->id,
            'tecnico_id' => $tecnico->id,
            'accion' => 'instalacion',
            'observaciones' => 'Instalacion de RAM',
        ]);
    });

    test('item cannot be assigned if already assigned', function () {
        $item = Item::factory()->instalado()->create();
        $equipo = Equipo::factory()->create();
        Grupo::factory()->create(['item_id' => $item->id, 'equipo_id' => $equipo->id]);

        $otroEquipo = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $otroEquipo))
            ->post(route('admin.sti.items.asignar', $item), [
                'equipo_id' => $otroEquipo->id,
            ]);

        $response->assertSessionHasErrors(['asignar']);
    });

    test('asignar requires equipo_id', function () {
        $item = Item::factory()->create(['estado' => 'disponible']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.asignar', $item), []);

        $response->assertSessionHasErrors(['equipo_id']);
    });

    test('item can be retired from equipo', function () {
        $item = Item::factory()->instalado()->create();
        $equipo = Equipo::factory()->create();
        $tecnico = Tecnico::factory()->create(['activo' => true]);
        Grupo::factory()->create(['item_id' => $item->id, 'equipo_id' => $equipo->id]);

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $equipo))
            ->post(route('admin.sti.items.retirar', $item), [
                'tecnico_id' => $tecnico->id,
                'observaciones' => 'Retiro por falla',
            ]);

        $response->assertRedirect(route('admin.sti.equipos.edit', $equipo));

        // Grupo deleted
        $this->assertDatabaseMissing('sti_grupos', [
            'item_id' => $item->id,
        ]);

        // Estado changed
        $this->assertDatabaseHas('sti_items', [
            'id' => $item->id,
            'estado' => 'disponible',
        ]);

        // Historial created
        $this->assertDatabaseHas('sti_items_historial', [
            'item_id' => $item->id,
            'equipo_id' => $equipo->id,
            'tecnico_id' => $tecnico->id,
            'accion' => 'retiro',
            'observaciones' => 'Retiro por falla',
        ]);
    });

    test('item cannot be retired if not assigned', function () {
        $item = Item::factory()->create(['estado' => 'disponible']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.retirar', $item), []);

        $response->assertSessionHasErrors(['retirar']);
    });

    test('asignar works without tecnico', function () {
        $item = Item::factory()->create(['estado' => 'disponible']);
        $equipo = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $equipo))
            ->post(route('admin.sti.items.asignar', $item), [
                'equipo_id' => $equipo->id,
            ]);

        $response->assertRedirect(route('admin.sti.equipos.edit', $equipo));

        $this->assertDatabaseHas('sti_items_historial', [
            'item_id' => $item->id,
            'tecnico_id' => null,
            'accion' => 'instalacion',
        ]);
    });
});
