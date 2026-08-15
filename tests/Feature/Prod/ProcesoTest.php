<?php

use App\Models\Prod\Proceso;
use App\Models\Prod\ProcesoEvento;
use App\Models\User;

/**
 * Catalogo de procesos que se pagan como destajo y los numeros de evento del
 * export de planta que los disparan.
 *
 * El evento es unique en toda la tabla: si el mismo numero colgara de dos
 * procesos, un movimiento se cargaria a los dos y la pieza se pagaria doble.
 * Eso es lo que cuida la validacion de estas pruebas.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    // Sembrados por la migracion, con los eventos 75 y 85.
    $this->soldadura = Proceso::where('nombre', 'Soldadura')->firstOrFail();
    $this->pintura = Proceso::where('nombre', 'Pintura')->firstOrFail();
});

function guardarProceso(Proceso $proceso, array $eventos, ?string $nombre = null)
{
    return test()->actingAs(test()->user)
        ->put(route('admin.prod.procesos.update', $proceso), [
            'nombre' => $nombre ?? $proceso->nombre,
            'orden' => $proceso->orden,
            'activo' => true,
            'eventos' => $eventos,
        ]);
}

test('editar un proceso conservando su propio evento se guarda', function () {
    // El caso que reventaba en produccion: la regla unique excluia "el resto de
    // los procesos" con un where de tres argumentos, y el operador terminaba
    // comparandose como valor contra proceso_id.
    guardarProceso($this->soldadura, [
        ['evento' => '75', 'descripcion' => 'Soldadura de taller'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.prod.procesos.index'));

    expect(ProcesoEvento::where('evento', '75')->first())
        ->proceso_id->toBe($this->soldadura->id)
        ->descripcion->toBe('Soldadura de taller');
});

test('no se le puede robar un evento a otro proceso', function () {
    // El 85 es de Pintura.
    guardarProceso($this->soldadura, [
        ['evento' => '85', 'descripcion' => 'Intento de robo'],
    ])->assertSessionHasErrors('eventos.0.evento');

    expect(ProcesoEvento::where('evento', '85')->value('proceso_id'))->toBe($this->pintura->id);
});

test('se pueden agregar eventos nuevos junto al propio', function () {
    guardarProceso($this->soldadura, [
        ['evento' => '75', 'descripcion' => 'Soldadura'],
        ['evento' => '76', 'descripcion' => 'Soldadura especial'],
    ])->assertSessionHasNoErrors();

    expect($this->soldadura->eventos()->pluck('evento')->sort()->values()->all())->toBe(['75', '76']);
});

test('el evento que se quita queda libre para otro proceso', function () {
    // Se le quita el 75 a Soldadura...
    guardarProceso($this->soldadura, [['evento' => '76', 'descripcion' => null]])
        ->assertSessionHasNoErrors();

    expect(ProcesoEvento::where('evento', '75')->exists())->toBeFalse();

    // ...y ahora Pintura si lo puede tomar.
    guardarProceso($this->pintura, [
        ['evento' => '85', 'descripcion' => 'Pintura'],
        ['evento' => '75', 'descripcion' => 'Heredado'],
    ])->assertSessionHasNoErrors();

    expect(ProcesoEvento::where('evento', '75')->value('proceso_id'))->toBe($this->pintura->id);
});

function crearProceso(array $eventos, string $nombre = 'Granallado')
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.procesos.store'), [
            'nombre' => $nombre,
            'orden' => 3,
            'activo' => true,
            'eventos' => $eventos,
        ]);
}

test('crear un proceso con sus eventos', function () {
    crearProceso([
        ['evento' => '90', 'descripcion' => 'Granallado'],
        ['evento' => '91', 'descripcion' => null],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.prod.procesos.index'));

    $proceso = Proceso::where('nombre', 'Granallado')->firstOrFail();

    expect($proceso->eventos()->pluck('evento')->sort()->values()->all())->toBe(['90', '91']);
});

test('crear un proceso sin eventos se permite: se captura a mano', function () {
    crearProceso([])->assertSessionHasNoErrors();

    expect(Proceso::where('nombre', 'Granallado')->firstOrFail()->eventos()->count())->toBe(0);
});

test('al crear no se puede tomar un evento que ya es de otro proceso', function () {
    // El 75 es de Soldadura desde la migracion.
    crearProceso([['evento' => '75', 'descripcion' => 'Robo al crear']])
        ->assertSessionHasErrors('eventos.0.evento');

    expect(Proceso::where('nombre', 'Granallado')->exists())->toBeFalse()
        ->and(ProcesoEvento::where('evento', '75')->value('proceso_id'))->toBe($this->soldadura->id);
});

test('el mismo evento repetido en el formulario se rechaza', function () {
    // `unique` consulta la base, no el payload: sin `distinct` esto pasaba y se
    // guardaba un solo renglon, como si el segundo nunca se hubiera escrito.
    crearProceso([
        ['evento' => '90', 'descripcion' => 'Primero'],
        ['evento' => '90', 'descripcion' => 'Segundo'],
    ])->assertSessionHasErrors('eventos.0.evento');

    expect(Proceso::where('nombre', 'Granallado')->exists())->toBeFalse();
});

test('el mismo evento repetido al editar tambien se rechaza', function () {
    guardarProceso($this->soldadura, [
        ['evento' => '75', 'descripcion' => 'Uno'],
        ['evento' => '75', 'descripcion' => 'Otro'],
    ])->assertSessionHasErrors('eventos.0.evento');
});

test('un proceso con produccion capturada no se elimina', function () {
    $registro = \App\Models\Prod\Registro::factory()->create(['proceso_id' => $this->soldadura->id]);

    $this->actingAs($this->user)
        ->delete(route('admin.prod.procesos.destroy', $this->soldadura))
        ->assertSessionHasErrors('error');

    expect(Proceso::find($this->soldadura->id))->not->toBeNull()
        ->and($registro->fresh())->not->toBeNull();
});
