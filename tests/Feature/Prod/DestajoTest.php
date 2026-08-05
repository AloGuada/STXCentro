<?php

use App\Models\Prod\Asistencia;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
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
        $marca = marcaConPiezas(30, ['peso_unitario' => 10.000]);
        tarifaDeMarca($marca, 5.0000);
        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
        capturarPiezas($marca->piezas, $grupo, '2026-03-04');

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
        $marca = marcaConPiezas(5);

        $destajo = Destajo::factory()->create(['cerrado' => false, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);
        capturarPiezas($marca->piezas, $grupo, '2026-03-04');

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.asistencia', $destajo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/destajos/asistencia')
                ->has('grupos', 1)
                ->has('grupos.0.empleados', 1)
                // Lunes a sabado: el domingo no se captura.
                ->has('dias', 6)
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
                ->has('marcas')
                ->has('procesos')
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
        $marca = marcaConPiezas(20, ['peso_unitario' => 10.000]);
        tarifaDeMarca($marca, 5.0000);
        obraPagaProcesos($marca->obra_id);

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

        capturarPiezas($marca->piezas, $grupo, '2026-02-05');

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

        // Un renglon por pieza: el detalle es el libro de que QS se pago.
        expect($liquidacion->detalles)->toHaveCount(20);

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
        $marca = marcaConPiezas(20, ['peso_unitario' => 10.000]);
        tarifaDeMarca($marca, 5.0000);
        obraPagaProcesos($marca->obra_id);

        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        capturarPiezas($marca->piezas, $grupo, '2026-02-05');

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
        $marca = marcaConPiezas(3, ['peso_unitario' => 10.000]);
        // Sin tarifa para el proceso: la marca se pagaria en cero.

        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        capturarPiezas($marca->piezas, $grupo, '2026-02-05');

        $this->actingAs($this->user)
            ->get(route('admin.prod.destajos.show', $destajo))
            ->assertInertia(fn ($page) => $page->has('piezasSinPrecio', 1));
    });
});

describe('destajo produccion (registros)', function () {
    test('registro can be captured within destajo', function () {
        $marca = marcaConPiezas(7);
        obraPagaProcesos($marca->obra_id);
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [
                'fecha' => '2026-02-05',
                'piezas' => $marca->piezas->pluck('id')->all(),
                'proceso_id' => proceso()->id,
                'grupo_trabajo_id' => $grupo->id,
            ])
            ->assertRedirect(route('admin.prod.destajos.show', $destajo));

        // Un renglon por QS seleccionado.
        expect(Registro::where('grupo_trabajo_id', $grupo->id)->count())->toBe(7);
        $this->assertDatabaseHas('prod_registros', [
            'pieza_id' => $marca->piezas->first()->id,
            'proceso_id' => proceso()->id,
            'grupo_trabajo_id' => $grupo->id,
        ]);
    });

    test('registro fecha must be within destajo period', function () {
        $marca = marcaConPiezas(1);
        obraPagaProcesos($marca->obra_id);
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [
                'fecha' => '2026-02-20',
                'piezas' => [$marca->piezas->first()->id],
                'proceso_id' => proceso()->id,
                'grupo_trabajo_id' => $grupo->id,
            ])
            ->assertSessionHasErrors(['fecha']);
    });

    test('registro store requires all fields', function () {
        $destajo = Destajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.store', $destajo), [])
            ->assertSessionHasErrors(['fecha', 'piezas', 'proceso_id', 'grupo_trabajo_id']);
    });

    test('registro can be deleted from destajo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $destajo = Destajo::factory()->create();
        $registro = Registro::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.destajos.registros.destroy', [$destajo, $registro]))
            ->assertRedirect(route('admin.prod.destajos.show', $destajo));

        $this->assertDatabaseMissing('prod_registros', ['id' => $registro->id]);
    });

    test('csv import loads produccion to groups', function () {
        $marca = marcaConPiezas(2, ['marca' => 'V-01']);
        obraPagaProcesos($marca->obra_id);
        [$primera, $segunda] = [$marca->piezas[0], $marca->piezas[1]];
        GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);

        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $csv = "GRUPO,QS,PROCESO\n";
        $csv .= "Grupo A,{$primera->qs},Soldadura\n";
        $csv .= "Grupo A,{$segunda->qs},Soldadura\n";
        $file = UploadedFile::fake()->createWithContent('produccion.csv', $csv);

        $this->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $destajo), [
                'csv_file' => $file,
                'fecha' => '2026-02-05',
            ])
            ->assertRedirect();

        expect(Registro::count())->toBe(2);
        $this->assertDatabaseHas('prod_registros', ['pieza_id' => $primera->id]);
        $this->assertDatabaseHas('prod_registros', ['pieza_id' => $segunda->id]);
    });

    test('csv import reports unknown group', function () {
        $marca = marcaConPiezas(1, ['marca' => 'V-01']);
        obraPagaProcesos($marca->obra_id);
        $qs = $marca->piezas->first()->qs;
        $destajo = Destajo::factory()->create([
            'fecha_inicio' => '2026-02-03',
            'fecha_fin' => '2026-02-09',
        ]);

        $csv = "GRUPO,QS,PROCESO\nGrupo Fantasma,{$qs},Soldadura\n";
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
