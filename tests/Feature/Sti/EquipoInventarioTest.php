<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\Sti\ItemTipo;
use App\Models\Sti\Tecnico;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('equipo inventario tab', function () {
    test('edit page loads grupos with item and tipo', function () {
        $equipo = Equipo::factory()->create();
        $tipo = ItemTipo::factory()->create();
        $item = Item::factory()->instalado()->create(['tipo_id' => $tipo->id]);
        Grupo::factory()->create(['equipo_id' => $equipo->id, 'item_id' => $item->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.equipos.edit', $equipo));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/equipos/edit')
            ->has('equipo.grupos', 1)
            ->has('equipo.grupos.0.item')
            ->has('equipo.grupos.0.item.tipo')
            ->has('itemsDisponibles')
            ->has('tecnicos')
        );
    });

    test('items disponibles excludes assigned items', function () {
        $equipo = Equipo::factory()->create();
        $disponible = Item::factory()->create(['estado' => 'disponible']);
        $asignado = Item::factory()->instalado()->create();
        Grupo::factory()->create(['equipo_id' => $equipo->id, 'item_id' => $asignado->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.equipos.edit', $equipo));

        $response->assertInertia(fn ($page) => $page
            ->has('itemsDisponibles', 1)
            ->where('itemsDisponibles.0.id', $disponible->id)
        );
    });

    test('items en baja are excluded from disponibles', function () {
        Equipo::factory()->create();
        Item::factory()->baja()->create();
        $disponible = Item::factory()->create(['estado' => 'disponible']);

        $equipo = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.equipos.edit', $equipo));

        $response->assertInertia(fn ($page) => $page
            ->has('itemsDisponibles', 1)
            ->where('itemsDisponibles.0.id', $disponible->id)
        );
    });

    test('item can be assigned from equipo edit', function () {
        $equipo = Equipo::factory()->create();
        $item = Item::factory()->create(['estado' => 'disponible']);
        $tecnico = Tecnico::factory()->create(['activo' => true]);

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $equipo))
            ->post(route('admin.sti.items.asignar', $item), [
                'equipo_id' => $equipo->id,
                'tecnico_id' => $tecnico->id,
                'observaciones' => 'Asignado desde equipo',
            ]);

        $response->assertRedirect(route('admin.sti.equipos.edit', $equipo));

        $this->assertDatabaseHas('sti_grupos', [
            'equipo_id' => $equipo->id,
            'item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('sti_items', [
            'id' => $item->id,
            'estado' => 'instalado',
        ]);
    });

    test('item can be retired from equipo edit', function () {
        $equipo = Equipo::factory()->create();
        $item = Item::factory()->instalado()->create();
        $tecnico = Tecnico::factory()->create(['activo' => true]);
        Grupo::factory()->create(['equipo_id' => $equipo->id, 'item_id' => $item->id]);

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.equipos.edit', $equipo))
            ->post(route('admin.sti.items.retirar', $item), [
                'tecnico_id' => $tecnico->id,
                'observaciones' => 'Retirado desde equipo',
            ]);

        $response->assertRedirect(route('admin.sti.equipos.edit', $equipo));

        $this->assertDatabaseMissing('sti_grupos', [
            'item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('sti_items', [
            'id' => $item->id,
            'estado' => 'disponible',
        ]);
    });

    test('only active tecnicos are passed', function () {
        $equipo = Equipo::factory()->create();
        Tecnico::factory()->create(['activo' => true]);
        Tecnico::factory()->create(['activo' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.equipos.edit', $equipo));

        $response->assertInertia(fn ($page) => $page
            ->has('tecnicos', 1)
        );
    });
});
