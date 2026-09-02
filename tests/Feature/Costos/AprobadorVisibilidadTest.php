<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\AprobacionSolicitud;
use App\Models\Costos\Permiso;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.ver-departamentos-aprobador',
        'costos.solicitudes-pago.ver',
        'costos.solicitudes-pago.ver-departamentos-aprobador',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->deptoA = Departamento::factory()->create();
    $this->deptoB = Departamento::factory()->create();
    $this->otro = User::factory()->create();
});

/** Asigna al usuario como aprobador del departamento para un tipo. */
function asignarAprobador(User $user, Departamento $depto, string $tipo): void
{
    $permiso = Permiso::create([
        'descripcion' => 'Jefe',
        'nivel' => 2,
        'tipo_aprobacion' => $tipo,
        'omitir_si_presupuesto_reservado' => false,
    ]);
    AprobacionDepartamento::create([
        'departamento_id' => $depto->id,
        'permiso_id' => $permiso->id,
        'aprobador_id' => $user->id,
    ]);
}

test('el aprobador ve las requisiciones de su departamento con el permiso', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-departamentos-aprobador']);
    asignarAprobador($aprobador, $this->deptoA, 'requisicion');

    $reqA = Requisicion::factory()->create(['departamento_id' => $this->deptoA->id, 'solicitante_id' => $this->otro->id]);
    Requisicion::factory()->create(['departamento_id' => $this->deptoB->id, 'solicitante_id' => $this->otro->id]);

    $this->actingAs($aprobador)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/costos/requisiciones/index')
            ->has('requisiciones.data', 1)
            ->where('requisiciones.data.0.folio', $reqA->folio)
        );
});

test('sin el permiso, el aprobador NO ve las requisiciones ajenas de su departamento', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo(['costos.requisiciones.ver']); // sin ver-departamentos-aprobador
    asignarAprobador($aprobador, $this->deptoA, 'requisicion');

    Requisicion::factory()->create(['departamento_id' => $this->deptoA->id, 'solicitante_id' => $this->otro->id]);

    $this->actingAs($aprobador)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requisiciones.data', 0));
});

test('la visibilidad respeta el tipo: aprobar solicitudes no muestra requisiciones', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-departamentos-aprobador']);
    // Es aprobador del depto A pero SOLO para solicitudes de pago.
    asignarAprobador($aprobador, $this->deptoA, 'solicitud_pago');

    Requisicion::factory()->create(['departamento_id' => $this->deptoA->id, 'solicitante_id' => $this->otro->id]);

    $this->actingAs($aprobador)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requisiciones.data', 0));
});

test('el aprobador ve las solicitudes de pago de su departamento con el permiso', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-departamentos-aprobador']);
    asignarAprobador($aprobador, $this->deptoA, 'solicitud_pago');

    $solA = SolicitudPago::factory()->create(['departamento_id' => $this->deptoA->id, 'solicitante_id' => $this->otro->id]);
    SolicitudPago::factory()->create(['departamento_id' => $this->deptoB->id, 'solicitante_id' => $this->otro->id]);

    $this->actingAs($aprobador)
        ->get(route('admin.costos.solicitudes-pago.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/costos/solicitudes-pago/index')
            ->has('solicitudes.data', 1)
            ->where('solicitudes.data.0.folio', $solA->folio)
        );
});

test('sin el permiso, el aprobador NO ve las solicitudes ajenas de su departamento', function () {
    $aprobador = User::factory()->create();
    $aprobador->givePermissionTo(['costos.solicitudes-pago.ver']); // sin ver-departamentos-aprobador
    asignarAprobador($aprobador, $this->deptoA, 'solicitud_pago');

    SolicitudPago::factory()->create(['departamento_id' => $this->deptoA->id, 'solicitante_id' => $this->otro->id]);

    $this->actingAs($aprobador)
        ->get(route('admin.costos.solicitudes-pago.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('solicitudes.data', 0));
});

/**
 * La vista supervisora renderiza la misma pantalla con el mismo builder, asi
 * que hereda la carga diferida. Se prueba aparte porque su ruta lleva el
 * aprobador en la URL: la recarga parcial tiene que seguir apuntando a ese
 * usuario y no al que esta mirando.
 */
describe('la bandeja supervisora tambien difiere el historial', function () {
    test('la carga inicial no trae el historial del aprobador observado', function () {
        $observado = User::factory()->create();
        $solicitud = SolicitudPago::factory()->create();
        AprobacionSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'nivel' => 1,
            'aprobador_id' => $observado->id,
            'estatus' => 'aprobada',
            'fecha_respuesta' => now(),
        ]);

        // La ruta va con `role:super-admin`, no con un permiso suelto.
        $supervisor = User::factory()->create(['firma_path' => 'firmas/test.png']);
        $supervisor->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

        $this->actingAs($supervisor)
            ->get(route('admin.costos.aprobaciones.bandeja', $observado))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('soloLectura', true)
                ->missing('aprobadas')
                ->where('conteos.aprobadas', 1)
            );
    });
});
