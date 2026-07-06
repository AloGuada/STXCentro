<?php

use App\Enums\Costos\PresupuestoEstatus;
use App\Exceptions\Costos\InvalidStateTransitionException;
use App\Models\Cob\Partida;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('entidad presupuesto', function () {
    test('un presupuesto puede colgar de proyecto, obra o partida', function () {
        $proyecto = Proyecto::factory()->create();
        $obra = Obra::factory()->create();
        $partida = Partida::factory()->create();

        $pProyecto = Presupuesto::factory()->paraProyecto($proyecto)->create();
        $pObra = Presupuesto::factory()->paraObra($obra)->create();
        $pPartida = Presupuesto::factory()->paraPartida($partida)->create();

        expect($pProyecto->presupuestable)->toBeInstanceOf(Proyecto::class);
        expect($pObra->presupuestable)->toBeInstanceOf(Obra::class);
        expect($pPartida->presupuestable)->toBeInstanceOf(Partida::class);
    });

    test('nombreMostrar usa el nombre interno o cae al del presupuestable', function () {
        $obra = Obra::factory()->create(['no' => 'OP-123']);

        $sinNombre = Presupuesto::factory()->paraObra($obra)->create(['nombre_interno' => null]);
        $conNombre = Presupuesto::factory()->paraObra(Obra::factory()->create())->create(['nombre_interno' => 'Torre A']);

        expect($sinNombre->nombreMostrar())->toBe('OP-123');
        expect($conNombre->nombreMostrar())->toBe('Torre A');
    });

    test('nace activo y no está cerrado', function () {
        $presupuesto = Presupuesto::factory()->create();

        expect($presupuesto->estatus)->toBe(PresupuestoEstatus::Activo);
        expect($presupuesto->estaCerrado())->toBeFalse();
    });

    test('cambiar estado cierra y reabre el presupuesto', function () {
        $presupuesto = Presupuesto::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.estado', $presupuesto))
            ->assertRedirect();

        expect($presupuesto->fresh()->estaCerrado())->toBeTrue();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.estado', $presupuesto))
            ->assertRedirect();

        expect($presupuesto->fresh()->estaCerrado())->toBeFalse();
    });

    test('estaCerrado del renglón refleja el estatus del presupuesto', function () {
        $presupuesto = Presupuesto::factory()->cerrado()->create();
        $obraRubro = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);

        expect($obraRubro->estaCerrado())->toBeTrue();
    });

    test('actualizar nombre interno', function () {
        $presupuesto = Presupuesto::factory()->create(['nombre_interno' => null]);

        $this->actingAs($this->user)
            ->put(route('admin.costos.presupuestos.update', $presupuesto), [
                'nombre_interno' => 'Presupuesto Norte',
            ])->assertRedirect();

        expect($presupuesto->fresh()->nombre_interno)->toBe('Presupuesto Norte');
    });

    test('una transición inválida lanza excepción', function () {
        $presupuesto = Presupuesto::factory()->create();

        $presupuesto->transitionTo(PresupuestoEstatus::Activo);
    })->throws(InvalidStateTransitionException::class);
});
