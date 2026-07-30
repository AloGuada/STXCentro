<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Asistencia;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin destajos', function () {
    test('index page can be rendered', function () {
        Destajo::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/destajos/index')
                ->has('destajos.data', 3)
            );
    });

    test('create page can be rendered', function () {
        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/prod/destajos/create'));
    });

    test('destajo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.store'), [
                'anio' => 2026,
                'semana' => 6,
                'fecha_inicio' => '2026-02-03',
                'fecha_fin' => '2026-02-09',
            ]);

        $destajo = Destajo::first();
        $response->assertRedirect(route('admin.prod.destajos.show', $destajo));

        $this->assertDatabaseHas('prod_destajos', [
            'anio' => 2026,
            'semana' => 6,
            'cerrado' => false,
        ]);
    });

    test('orden de pago pdf se genera en destajo abierto', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
        GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);
        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
        Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $concepto->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 30]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.orden-pago', $destajo));

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    test('orden de pago pdf se genera en destajo sin datos', function () {
        $destajo = Destajo::factory()->create(['cerrado' => false]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.orden-pago', $destajo))
            ->assertOk();
    });

    test('pantalla de asistencia lista grupos, empleados y dias del periodo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);
        $concepto = Concepto::factory()->create();

        $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
        Registro::factory()->create(['fecha' => '2026-03-04', 'concepto_id' => $concepto->id, 'grupo_trabajo_id' => $grupo->id, 'cantidad' => 5]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.asistencia', $destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/destajos/asistencia')
                ->has('grupos', 1)
                ->has('grupos.0.empleados', 1)
                ->has('dias', 7)
            );
    });

    test('destajo semana is unique per year', function () {
        Destajo::factory()->create(['anio' => 2026, 'semana' => 6]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.store'), [
                'anio' => 2026,
                'semana' => 6,
                'fecha_inicio' => '2026-02-03',
                'fecha_fin' => '2026-02-09',
            ])
            ->assertSessionHasErrors(['semana']);

        expect(Destajo::where('anio', 2026)->where('semana', 6)->count())->toBe(1);
    });

    test('destajo semana cannot exceed 52', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.store'), [
                'anio' => 2026,
                'semana' => 53,
                'fecha_inicio' => '2026-02-03',
                'fecha_fin' => '2026-02-09',
            ])
            ->assertSessionHasErrors(['semana']);
    });

    test('show page renders with liquidaciones', function () {
        $destajo = Destajo::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/destajos/show')
                ->has('destajo')
            );
    });

    test('show page includes preview and catalogos for open destajo', function () {
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/destajos/show')
                ->has('registrosPreview')
                ->has('pagosExtraPreview')
                ->has('piezasSinPrecio')
                ->has('gruposTrabajo')
                ->has('conceptos')
                ->has('tipos')
            );
    });

    test('destajo can be deleted if not cerrado', function () {
        $destajo = Destajo::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.destroy', $destajo))
            ->assertRedirect(route('admin.prod.destajos.index'));

        $this->assertDatabaseMissing('prod_destajos', ['id' => $destajo->id]);
    });

    test('destajo cannot be deleted if cerrado', function () {
        $destajo = Destajo::factory()->cerrado()->create();

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.destroy', $destajo))
            ->assertSessionHasErrors(['error']);

        $this->assertDatabaseHas('prod_destajos', ['id' => $destajo->id]);
    });

    test('cerrar generates liquidaciones correctly', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
        GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'nombre' => 'Juan',
            'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 600])->id,
        ]);
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'nombre' => 'Pedro',
            'categoria_empleado_id' => CategoriaEmpleado::factory()->create(['valor' => 400])->id,
        ]);

        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        Registro::factory()->create([
            'fecha' => '2026-02-05',
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 20,
        ]);

        // Sin asistencia completa el cierre queda bloqueado.
        foreach ($grupo->empleados as $empleado) {
            for ($dia = 3; $dia <= 9; $dia++) {
                Asistencia::factory()->create([
                    'destajo_id' => $destajo->id,
                    'grupo_empleado_id' => $empleado->id,
                    'fecha' => sprintf('2026-02-%02d', $dia),
                ]);
            }
        }

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo))
            ->assertRedirect();

        $destajo->refresh();
        expect($destajo->cerrado)->toBeTrue();
        expect($destajo->fecha_cierre)->not->toBeNull();

        // 20 piezas * 10 kg/u = 200 kg, 200 kg * 5 $/kg = 1000
        $liquidacion = Liquidacion::where('destajo_id', $destajo->id)->first();
        expect($liquidacion)->not->toBeNull();
        expect((float) $liquidacion->total_kilos)->toBe(200.0);
        expect((float) $liquidacion->total_produccion)->toBe(1000.0);
        expect((float) $liquidacion->total_final)->toBe(1000.0);

        expect($liquidacion->detalles)->toHaveCount(1);
        expect($liquidacion->detalles->first()->cantidad)->toBe(20);

        // Sin salario minimo configurado el sueldo base es 0, asi que los 1000
        // son excedente y se reparten por el peso de la categoria: 600 y 400.
        expect($liquidacion->empleados)->toHaveCount(2);
        $juan = $liquidacion->empleados->firstWhere('nombre', 'Juan');
        expect((float) $juan->porcentaje)->toBe(60.0);
        expect((float) $juan->monto_asignado)->toBe(600.0);
        $pedro = $liquidacion->empleados->firstWhere('nombre', 'Pedro');
        expect((float) $pedro->monto_asignado)->toBe(400.0);
    });

    test('cerrar cannot be called twice', function () {
        $destajo = Destajo::factory()->cerrado()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo))
            ->assertSessionHasErrors(['error']);
    });

    test('cerrar includes pagos extra in totals', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
        $gp = GrupoPrecio::factory()->create(['obra_id' => $obra->id, 'precio_kilo' => 5.0000]);
        GrupoPrecioConcepto::create(['concepto_id' => $concepto->id, 'grupo_precio_id' => $gp->id]);

        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        Registro::factory()->create([
            'fecha' => '2026-02-05',
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 20,
        ]);

        $tipo = TipoPagoExtra::create(['descripcion' => 'Bono', 'orden' => 1, 'desgloce' => false]);
        PagoExtra::create([
            'descripcion' => 'Bono semanal',
            'tipo_id' => $tipo->id,
            'destajo_id' => $destajo->id,
            'grupo_trabajo_id' => $grupo->id,
            'precio' => 100.00,
            'dias' => 2,
            'personas' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.cerrar', $destajo))
            ->assertRedirect();

        $liquidacion = Liquidacion::where('destajo_id', $destajo->id)->first();
        expect((float) $liquidacion->total_produccion)->toBe(1000.0);
        expect((float) $liquidacion->total_extras)->toBe(200.0);
        expect((float) $liquidacion->total_final)->toBe(1200.0);
    });

    test('preview flags piezas sin precio', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id, 'peso_unitario' => 10.000]);
        // Sin GrupoPrecioConcepto: la pieza no tiene precio.

        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        Registro::factory()->create([
            'fecha' => '2026-02-05',
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 5,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $destajo))
            ->assertInertia(fn ($page) => $page->has('piezasSinPrecio', 1));
    });
});

describe('destajo produccion (registros)', function () {
    test('registro can be captured within destajo', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [
                'fecha' => '2026-02-05',
                'concepto_id' => $concepto->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => 7,
            ])
            ->assertRedirect(route('admin.prod.destajos.show', $destajo));

        $this->assertDatabaseHas('prod_registros', [
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 7,
        ]);
    });

    test('registro fecha must be within destajo period', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [
                'fecha' => '2026-02-20',
                'concepto_id' => $concepto->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => 7,
            ])
            ->assertSessionHasErrors(['fecha']);
    });

    test('registro store requires all fields', function () {
        $destajo = Destajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [])
            ->assertSessionHasErrors(['fecha', 'concepto_id', 'grupo_trabajo_id', 'cantidad']);
    });

    test('registro can be deleted from destajo', function () {
        $obra = Obra::factory()->create();
        $concepto = Concepto::factory()->create(['obra_id' => $obra->id]);
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create();
        $registro = Registro::factory()->create([
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.registros.destroy', [$destajo, $registro]))
            ->assertRedirect(route('admin.prod.destajos.show', $destajo));

        $this->assertDatabaseMissing('prod_registros', ['id' => $registro->id]);
    });

    test('csv import loads produccion to groups', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->create(['obra_id' => $obra->id, 'marca' => 'V-01', 'activo' => true]);
        Concepto::factory()->create(['obra_id' => $obra->id, 'marca' => 'C-03', 'activo' => true]);
        GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);

        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $csv = "GRUPO,MARCA,CANTIDAD\n";
        $csv .= "Grupo A,V-01,12\n";
        $csv .= "Grupo A,C-03,8\n";
        $file = UploadedFile::fake()->createWithContent('produccion.csv', $csv);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $destajo), [
                'csv_file' => $file,
                'fecha' => '2026-02-05',
            ])
            ->assertRedirect();

        expect(Registro::count())->toBe(2);
        $this->assertDatabaseHas('prod_registros', ['cantidad' => 12]);
        $this->assertDatabaseHas('prod_registros', ['cantidad' => 8]);
    });

    test('csv import reports unknown group', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->create(['obra_id' => $obra->id, 'marca' => 'V-01', 'activo' => true]);
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $csv = "GRUPO,MARCA,CANTIDAD\nGrupo Fantasma,V-01,5\n";
        $file = UploadedFile::fake()->createWithContent('produccion.csv', $csv);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $destajo), [
                'csv_file' => $file,
                'fecha' => '2026-02-05',
            ])
            ->assertSessionHasErrors(['csv_file']);

        expect(Registro::count())->toBe(0);
    });
});

describe('destajo pagos extra', function () {
    test('pago extra can be added to destajo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $tipo = TipoPagoExtra::create(['descripcion' => 'Bono', 'orden' => 1, 'desgloce' => false]);
        $destajo = Destajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.pagos-extra.store', $destajo), [
                'descripcion' => 'Bono semanal',
                'tipo_id' => $tipo->id,
                'grupo_trabajo_id' => $grupo->id,
                'precio' => 150,
                'dias' => 1,
                'personas' => 2,
            ])
            ->assertRedirect(route('admin.prod.destajos.show', $destajo));

        $this->assertDatabaseHas('prod_pagos_extra', [
            'destajo_id' => $destajo->id,
            'grupo_trabajo_id' => $grupo->id,
            'precio' => 150,
        ]);
    });

    test('pago extra cannot be added to cerrado destajo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $tipo = TipoPagoExtra::create(['descripcion' => 'Bono', 'orden' => 1, 'desgloce' => false]);
        $destajo = Destajo::factory()->cerrado()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.pagos-extra.store', $destajo), [
                'descripcion' => 'Bono semanal',
                'tipo_id' => $tipo->id,
                'grupo_trabajo_id' => $grupo->id,
                'precio' => 150,
                'dias' => 1,
                'personas' => 2,
            ])
            ->assertSessionHasErrors(['error']);
    });
});
