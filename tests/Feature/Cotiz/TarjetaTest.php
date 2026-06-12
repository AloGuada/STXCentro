<?php

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\Tarjeta;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
});

describe('cotiz tarjetas — CRUD', function () {
    test('index lista las tarjetas de la obra', function () {
        $obra = Obra::factory()->create();
        Tarjeta::factory()->count(2)->create(['obra_id' => $obra->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.tarjetas.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/tarjetas/index')
                ->has('obra')
                ->has('tarjetas', 2)
                ->has('tarjetas.0.registros_count')
                ->has('tarjetas.0.is_locked')
                ->has('generadoras')
            );
    });

    test('store crea una tarjeta y redirige al edit', function () {
        $obra = Obra::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.store'), [
                'obra_id' => $obra->id,
                'descripcion' => 'Columnas 4PLS',
                'orden' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjetas', [
            'obra_id' => $obra->id,
            'descripcion' => 'Columnas 4PLS',
        ]);
    });

    test('store con generadora_id vincula e importa sus registros', function () {
        $obra = Obra::factory()->create();
        $generadora = Generadora::factory()->create(['obra_id' => $obra->id]);
        GeneradoraRegistro::factory()->count(3)->create(['generadora_id' => $generadora->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.store'), [
                'obra_id' => $obra->id,
                'descripcion' => 'Con generadora',
                'generadora_id' => $generadora->id,
            ])
            ->assertRedirect();

        $tarjeta = Tarjeta::where('descripcion', 'Con generadora')->firstOrFail();

        expect($tarjeta->generadoras()->count())->toBe(1);
        expect($tarjeta->registros()->count())->toBe(3);
        $this->assertDatabaseHas('cotiz_tarjeta_registros', [
            'tarjeta_id' => $tarjeta->id,
        ]);
    });

    test('store valida campos requeridos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.store'), [])
            ->assertSessionHasErrors(['obra_id', 'descripcion']);
    });

    test('edit devuelve la tarjeta con generadoras, disponibles y lock', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        Generadora::factory()->count(2)->create(['obra_id' => $obra->id]);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.tarjetas.edit', $tarjeta))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/tarjetas/edit')
                ->has('tarjeta')
                ->has('tarjeta.generadoras')
                ->has('generadorasDisponibles', 2)
                ->has('lock', fn ($lock) => $lock
                    ->where('is_locked', false)
                    ->where('locked_by', null)
                    ->where('locked_at', null)
                )
            );
    });

    test('update modifica descripcion y orden', function () {
        $tarjeta = Tarjeta::factory()->create();

        $this->actingAs($this->user)
            ->put(route('admin.cotiz.tarjetas.update', $tarjeta), [
                'descripcion' => 'Nuevo nombre',
                'orden' => 9,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_tarjetas', [
            'id' => $tarjeta->id,
            'descripcion' => 'Nuevo nombre',
            'orden' => 9,
        ]);
    });

    test('destroy elimina la tarjeta', function () {
        $tarjeta = Tarjeta::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.tarjetas.destroy', $tarjeta))
            ->assertRedirect(route('admin.cotiz.tarjetas.index', $tarjeta->obra_id));

        $this->assertDatabaseMissing('cotiz_tarjetas', ['id' => $tarjeta->id]);
    });
});

describe('cotiz tarjetas — vínculo con generadoras', function () {
    test('vincular importa solo los registros con material y no ya importados', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $generadora = Generadora::factory()->create(['obra_id' => $obra->id]);
        GeneradoraRegistro::factory()->count(2)->create(['generadora_id' => $generadora->id]);
        // Un registro sin material de origen: NO debe importarse.
        GeneradoraRegistro::factory()->create([
            'generadora_id' => $generadora->id,
            'material_origen_id' => null,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.generadoras.vincular', $tarjeta), [
                'generadora_id' => $generadora->id,
            ])
            ->assertRedirect();

        expect($tarjeta->registros()->count())->toBe(2);
        expect($tarjeta->generadoras()->count())->toBe(1);
    });

    test('un mismo generadora_registro no se importa en dos tarjetas', function () {
        $obra = Obra::factory()->create();
        $generadora = Generadora::factory()->create(['obra_id' => $obra->id]);
        GeneradoraRegistro::factory()->count(2)->create(['generadora_id' => $generadora->id]);

        $t1 = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $t2 = Tarjeta::factory()->create(['obra_id' => $obra->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.generadoras.vincular', $t1), ['generadora_id' => $generadora->id])
            ->assertRedirect();
        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.generadoras.vincular', $t2), ['generadora_id' => $generadora->id])
            ->assertRedirect();

        expect($t1->registros()->count())->toBe(2);
        expect($t2->registros()->count())->toBe(0);
    });

    test('desvincular quita los registros importados y el vínculo', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $generadora = Generadora::factory()->create(['obra_id' => $obra->id]);
        GeneradoraRegistro::factory()->count(2)->create(['generadora_id' => $generadora->id]);

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.tarjetas.generadoras.vincular', $tarjeta), ['generadora_id' => $generadora->id]);

        expect($tarjeta->registros()->count())->toBe(2);

        $this->actingAs($this->user)
            ->delete(route('admin.cotiz.tarjetas.generadoras.desvincular', [$tarjeta, $generadora]))
            ->assertRedirect();

        expect($tarjeta->registros()->count())->toBe(0);
        expect($tarjeta->generadoras()->count())->toBe(0);
    });
});

describe('cotiz tarjetas — lock', function () {
    test('POST lock/tarjeta/{id} toma el lock', function () {
        $tarjeta = Tarjeta::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'tarjeta', 'id' => $tarjeta->id]))
            ->assertOk();

        expect($tarjeta->fresh()->locked_by)->toBe($this->user->id);
    });

    test('un segundo usuario recibe 423 si el lock está vigente', function () {
        $tarjeta = Tarjeta::factory()->create();
        $otro = User::factory()->create();
        $otro->assignRole('usuario-cotiz');

        $this->actingAs($otro)
            ->post(route('admin.cotiz.lock', ['type' => 'tarjeta', 'id' => $tarjeta->id]))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('admin.cotiz.lock', ['type' => 'tarjeta', 'id' => $tarjeta->id]))
            ->assertStatus(423);
    });

    test('el index NO marca como bloqueado el lock propio', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $tarjeta->lock($this->user->id); // lo bloqueo yo mismo

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.tarjetas.index', $obra))
            ->assertInertia(fn ($page) => $page
                ->where('tarjetas.0.is_locked', false)
                ->where('tarjetas.0.locked_by', null)
            );
    });

    test('el index SÍ marca como bloqueado el lock de otro usuario', function () {
        $obra = Obra::factory()->create();
        $otro = User::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);
        $tarjeta->lock($otro->id);

        $this->actingAs($this->user)
            ->get(route('admin.cotiz.tarjetas.index', $obra))
            ->assertInertia(fn ($page) => $page
                ->where('tarjetas.0.is_locked', true)
                ->where('tarjetas.0.locked_by.id', $otro->id)
            );
    });
});
