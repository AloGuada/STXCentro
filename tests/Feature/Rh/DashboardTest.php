<?php

use App\Models\Departamento;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Puesto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'rh.puestos.ver', 'guard_name' => 'web']);
    $this->user->givePermissionTo('rh.puestos.ver');
});

describe('admin rh dashboard', function () {
    test('dashboard page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/dashboard/index')
            ->has('tree')
            ->has('kpis')
            ->has('departamentos')
        );
    });

    test('tree contains correct hierarchy with employee counts', function () {
        $departamento = Departamento::factory()->create();
        $jefe = Puesto::factory()->create(['departamento_id' => $departamento->id]);
        $subordinado = Puesto::factory()->create([
            'departamento_id' => $departamento->id,
            'puesto_jefe_id' => $jefe->id,
        ]);

        PeriodoLaboral::factory()->create(['puesto_id' => $subordinado->id, 'estado' => 'activo']);
        PeriodoLaboral::factory()->create(['puesto_id' => $subordinado->id, 'estado' => 'activo']);
        PeriodoLaboral::factory()->create(['puesto_id' => $subordinado->id, 'estado' => 'terminado']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/dashboard/index')
            ->where('kpis.total_puestos', 2)
            ->where('kpis.total_empleados', 2)
            ->where('kpis.puestos_vacantes', 1)
        );
    });

    test('unauthorized user cannot access dashboard', function () {
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)
            ->get(route('admin.rh.dashboard.index'))
            ->assertForbidden();
    });
});
