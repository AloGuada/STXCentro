<?php

use App\Models\Costos\Factura;
use App\Models\Costos\Permiso;
use App\Models\Proveedor;
use App\Models\User;

beforeEach(function () {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.facturas.ver');
});

describe('ordenamiento server-side de tablas Costos', function () {
    test('ordena por columna directa asc y desc', function () {
        Permiso::factory()->create(['descripcion' => 'Zeta', 'nivel' => 1]);
        Permiso::factory()->create(['descripcion' => 'Alfa', 'nivel' => 2]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.index', ['sort_by' => 'descripcion', 'sort_dir' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->where('sortBy', 'descripcion')
                ->where('sortDir', 'asc')
                ->where('permisos.data.0.descripcion', 'Alfa')
            );

        $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.index', ['sort_by' => 'descripcion', 'sort_dir' => 'desc']))
            ->assertInertia(fn ($page) => $page
                ->where('sortDir', 'desc')
                ->where('permisos.data.0.descripcion', 'Zeta')
            );
    });

    test('columna no permitida cae al orden por defecto', function () {
        Permiso::factory()->create(['descripcion' => 'B', 'nivel' => 2]);
        Permiso::factory()->create(['descripcion' => 'A', 'nivel' => 1]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.index', ['sort_by' => 'columna_invalida', 'sort_dir' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->where('sortBy', 'nivel')
                ->where('permisos.data.0.nivel', 1)
            );
    });

    test('sin parámetros de orden usa el default (created_at) sin reventar', function () {
        Factura::factory()->create(['folio' => 'F-A']);
        Factura::factory()->create(['folio' => 'F-B']);

        $this->actingAs($this->user)
            ->get(route('admin.costos.facturas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sortBy', 'created_at')
                ->where('sortDir', 'desc')
            );
    });

    test('ordena por relación de un salto (proveedor) vía subquery', function () {
        $zeta = Proveedor::factory()->create(['razon_social' => 'Zeta SA']);
        $alfa = Proveedor::factory()->create(['razon_social' => 'Alfa SA']);
        Factura::factory()->create(['proveedor_id' => $zeta->id, 'folio' => 'F-1']);
        Factura::factory()->create(['proveedor_id' => $alfa->id, 'folio' => 'F-2']);

        $this->actingAs($this->user)
            ->get(route('admin.costos.facturas.index', ['sort_by' => 'proveedor', 'sort_dir' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->where('sortBy', 'proveedor')
                ->where('facturas.data.0.folio', 'F-2')
            );
    });
});
