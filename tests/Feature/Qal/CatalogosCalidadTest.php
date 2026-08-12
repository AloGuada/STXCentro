<?php

use App\Models\Qal\DefectoSoldadura;
use App\Models\Qal\Equipo;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Soldador;
use App\Models\Qal\TipoPieza;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Deja al usuario con exactamente los permisos que se le pidan, para poder
 * comprobar que cada catálogo respeta el suyo.
 */
function usuarioCon(array $permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('la pantalla de catalogos abre con el permiso de ver de cualquiera de sus listas', function () {
    $usuario = usuarioCon(['qal.equipos.ver']);

    $this->actingAs($usuario)
        ->get(route('admin.qal.catalogos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/catalogos/index'));
});

test('la pantalla queda cerrada sin ningun permiso de calidad', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.qal.catalogos.index'))
        ->assertForbidden();
});

test('se da de alta un valor y nace activo', function () {
    $usuario = usuarioCon(['qal.equipos.ver', 'qal.equipos.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.equipos.store'), ['nombre' => 'FICEP Gemini'])
        ->assertRedirect();

    $equipo = Equipo::firstWhere('nombre', 'FICEP Gemini');

    expect($equipo)->not->toBeNull()
        ->and($equipo->activo)->toBeTrue();
});

test('no se admite el mismo valor dos veces en la misma lista', function () {
    Equipo::factory()->create(['nombre' => 'FICEP Gemini']);
    $usuario = usuarioCon(['qal.equipos.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.equipos.store'), ['nombre' => 'FICEP Gemini'])
        ->assertSessionHasErrors('nombre');

    expect(Equipo::count())->toBe(1);
});

test('crear exige su propio permiso, verlo no alcanza', function () {
    $usuario = usuarioCon(['qal.equipos.ver']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.equipos.store'), ['nombre' => 'Sierra cinta'])
        ->assertForbidden();

    expect(Equipo::count())->toBe(0);
});

test('editar cambia el nombre sin tocar el estado', function () {
    $defecto = DefectoSoldadura::factory()->create(['nombre' => 'Socabación']);
    $usuario = usuarioCon(['qal.defectos-soldadura.editar']);

    $this->actingAs($usuario)
        ->put(route('admin.qal.catalogos.defectos-soldadura.update', $defecto), ['nombre' => 'Socavación'])
        ->assertRedirect();

    expect($defecto->refresh()->nombre)->toBe('Socavación')
        ->and($defecto->activo)->toBeTrue();
});

/**
 * La baja de estos catálogos es desactivar. Si existiera un borrado, los
 * reportes ya emitidos citarían valores que dejaron de existir.
 */
test('desactivar y reactivar es la unica baja que hay', function () {
    $equipo = Equipo::factory()->create();
    $usuario = usuarioCon(['qal.equipos.editar']);

    $this->actingAs($usuario)
        ->patch(route('admin.qal.catalogos.equipos.toggle', $equipo))
        ->assertRedirect();

    expect($equipo->refresh()->activo)->toBeFalse();

    $this->actingAs($usuario)->patch(route('admin.qal.catalogos.equipos.toggle', $equipo));

    expect($equipo->refresh()->activo)->toBeTrue();
});

test('ningun catalogo expone una ruta de borrado', function () {
    $rutas = collect(app('router')->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'admin.qal.catalogos.'))
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true));

    expect($rutas)->toBeEmpty();
});

test('el prefijo del tipo de pieza se guarda en mayusculas y sin espacios', function () {
    $usuario = usuarioCon(['qal.tipos-pieza.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.tipos-pieza.store'), [
            'prefijo' => '  tp  ',
            'descripcion' => 'Trabe principal',
        ])
        ->assertRedirect();

    expect(TipoPieza::firstWhere('descripcion', 'Trabe principal')->prefijo)->toBe('TP');
});

test('el prefijo no admite guiones, que es lo que separa los tramos de la marca', function () {
    $usuario = usuarioCon(['qal.tipos-pieza.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.tipos-pieza.store'), [
            'prefijo' => 'TP-1',
            'descripcion' => 'Trabe principal',
        ])
        ->assertSessionHasErrors('prefijo');
});

/**
 * La marca viene como OBRA-PREFIJO-CONSECUTIVO, así que el prefijo abre el
 * SEGUNDO tramo. Y se prueba del más largo al más corto: si no, `CMV` se
 * resolvería como `CM` y `RCV` como `R`.
 */
test('el tipo se deduce del segundo tramo de la marca', function () {
    TipoPieza::factory()->create(['prefijo' => 'CM', 'descripcion' => 'Columna metálica']);
    $viento = TipoPieza::factory()->create(['prefijo' => 'CMV', 'descripcion' => 'Columna de viento']);
    $riostra = TipoPieza::factory()->create(['prefijo' => 'R', 'descripcion' => 'Riostra']);
    $roldana = TipoPieza::factory()->create(['prefijo' => 'RCV', 'descripcion' => 'Roldana de contraviento']);

    expect(TipoPieza::paraMarca('PIP-CMV3-22')->id)->toBe($viento->id)
        ->and(TipoPieza::paraMarca('PJ-RCV-8')->id)->toBe($roldana->id)
        ->and(TipoPieza::paraMarca('PJ-R2-8')->id)->toBe($riostra->id);
});

test('una marca sin segundo tramo no deduce nada', function () {
    TipoPieza::factory()->create(['prefijo' => 'TP', 'descripcion' => 'Trabe principal']);

    expect(TipoPieza::paraMarca('TP12'))->toBeNull()
        ->and(TipoPieza::paraMarca(''))->toBeNull();
});

test('un tipo desactivado deja de deducirse', function () {
    TipoPieza::factory()->inactivo()->create(['prefijo' => 'TP', 'descripcion' => 'Trabe principal']);

    expect(TipoPieza::paraMarca('PIP-TP12-3'))->toBeNull();
});

test('la clave del soldador es unica: es la que enlaza con su WPQR', function () {
    Soldador::factory()->create(['clave' => 'APC']);
    $usuario = usuarioCon(['qal.soldadores.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.soldadores.store'), [
            'nombre' => 'Otro soldador',
            'clave' => 'apc',
        ])
        ->assertSessionHasErrors('clave');
});

test('el soldador puede quedarse sin clave mientras se le consigue', function () {
    $usuario = usuarioCon(['qal.soldadores.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.soldadores.store'), ['nombre' => 'Angel Perez', 'clave' => ''])
        ->assertRedirect();

    expect(Soldador::firstWhere('nombre', 'Angel Perez')->clave)->toBeNull();
});

/**
 * Sin fecha no se puede afirmar que una certificación esté vencida, así que no
 * se da por vencida: sólo se avisa de lo que se sabe.
 */
test('solo se marca vencida la certificacion con fecha pasada', function () {
    $vencido = Soldador::factory()->certificacionVencida()->create();
    $vigente = Soldador::factory()->create();
    $sinFecha = Soldador::factory()->create(['certificacion_vence_at' => null]);

    expect($vencido->certificacionVencida())->toBeTrue()
        ->and($vigente->certificacionVencida())->toBeFalse()
        ->and($sinFecha->certificacionVencida())->toBeFalse();
});

test('el laboratorio guarda sus siglas, que es como se le nombra en el informe', function () {
    $usuario = usuarioCon(['qal.laboratorios.crear']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.catalogos.laboratorios.store'), [
            'nombre' => 'PILOMEX',
            'siglas' => 'PLX',
        ])
        ->assertRedirect();

    expect(Laboratorio::firstWhere('nombre', 'PILOMEX')->siglas)->toBe('PLX');
});

/**
 * `PJ-CM1-10` va después de `PJ-CM1-5`, no entre el 1 y el 2. Buscar un valor
 * entre 86 soldadores sólo es rápido si el orden es el que uno espera.
 */
test('el orden es alfabetico natural, no lexicografico', function () {
    Equipo::factory()->create(['nombre' => 'Rack 10']);
    Equipo::factory()->create(['nombre' => 'Rack 2']);
    Equipo::factory()->create(['nombre' => 'Rack 1']);

    $orden = Equipo::ordenNatural(Equipo::all())->pluck('nombre')->all();

    expect($orden)->toBe(['Rack 1', 'Rack 2', 'Rack 10']);
});
