<?php

use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
use Database\Seeders\Rh\PersonasSeeder;

/** Seeder con datos de prueba, para no depender del archivo de padrón real. */
class PersonasSeederDePrueba extends PersonasSeeder
{
    /** @var list<array<string, string|null>> */
    public static array $filas = [];

    /**
     * @return list<array<string, string|null>>
     */
    protected function filas(): array
    {
        return static::$filas;
    }
}

function sembrarPersonas(array $filas): void
{
    PersonasSeederDePrueba::$filas = $filas;
    (new PersonasSeederDePrueba)->run();
}

test('da de alta a quien no existe', function () {
    sembrarPersonas([
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'curp' => 'PEJU900101HDFRRN01', 'rfc' => 'PEJU900101AB1', 'nss' => '12345678901'],
    ]);

    $persona = Persona::sole();

    expect($persona->nombre_completo)->toBe('Juan Perez')
        ->and($persona->curp)->toBe('PEJU900101HDFRRN01')
        ->and($persona->imss)->toBe('12345678901');
});

test('si ya existe por curp actualiza en vez de duplicar', function () {
    $existente = Persona::factory()->create([
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'curp' => 'PEJU900101HDFRRN01',
        'rfc' => null,
    ]);

    sembrarPersonas([
        ['nombre' => 'Juan Carlos', 'apellido' => 'Perez Lopez', 'curp' => 'PEJU900101HDFRRN01', 'rfc' => 'PEJU900101AB1'],
    ]);

    expect(Persona::count())->toBe(1);

    // Gana el archivo: pisa el nombre viejo y rellena lo que faltaba.
    $existente->refresh();
    expect($existente->nombre)->toBe('Juan Carlos')
        ->and($existente->apellido)->toBe('Perez Lopez')
        ->and($existente->rfc)->toBe('PEJU900101AB1');
});

test('cruza la curp sin importar mayusculas ni espacios', function () {
    Persona::factory()->create(['curp' => 'peju900101hdfrrn01']);

    sembrarPersonas([
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'curp' => '  PEJU900101HDFRRN01  '],
    ]);

    expect(Persona::count())->toBe(1);
});

test('un campo vacio del archivo no borra lo que RH ya tenia', function () {
    $existente = Persona::factory()->create(['curp' => 'PEJU900101HDFRRN01', 'rfc' => 'RFCVIEJO123']);

    sembrarPersonas([
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'curp' => 'PEJU900101HDFRRN01', 'rfc' => null],
    ]);

    expect($existente->refresh()->rfc)->toBe('RFCVIEJO123');
});

test('el numero se escribe en el periodo laboral vigente', function () {
    $persona = Persona::factory()->create(['curp' => 'PEJU900101HDFRRN01']);
    $vigente = PeriodoLaboral::factory()->create([
        'persona_id' => $persona->id,
        'estado' => 'activo',
        'numero_empleado' => null,
    ]);

    sembrarPersonas([
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'curp' => 'PEJU900101HDFRRN01', 'numero' => 'E123'],
    ]);

    expect($vigente->refresh()->numero_empleado)->toBe('E123');
});

test('no inventa contratos: sin periodo vigente el numero no se guarda', function () {
    sembrarPersonas([
        ['nombre' => 'Eventual', 'apellido' => 'Sin Contrato', 'curp' => 'PEJU900101HDFRRN01', 'numero' => 'E999'],
    ]);

    expect(Persona::count())->toBe(1)
        ->and(PeriodoLaboral::count())->toBe(0);
});

test('sin curp cruza por rfc y luego por numero de empleado', function () {
    $porRfc = Persona::factory()->create(['curp' => null, 'rfc' => 'PEJU900101AB1']);

    $porNumero = Persona::factory()->create(['curp' => null, 'rfc' => null]);
    PeriodoLaboral::factory()->create([
        'persona_id' => $porNumero->id,
        'estado' => 'activo',
        'numero_empleado' => 'E555',
    ]);

    sembrarPersonas([
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'rfc' => 'PEJU900101AB1'],
        ['nombre' => 'Pedro', 'apellido' => 'Lopez', 'numero' => 'E555'],
    ]);

    expect(Persona::count())->toBe(2)
        ->and($porRfc->refresh()->nombre)->toBe('Juan')
        ->and($porNumero->refresh()->nombre)->toBe('Pedro');
});

test('sin ningun identificador cruza por nombre para no duplicar', function () {
    $filas = [['nombre' => 'Dario Manuel', 'apellido' => 'Leon Mendoza', 'numero' => 'C-3653']];

    sembrarPersonas($filas);
    sembrarPersonas($filas);

    expect(Persona::count())->toBe(1);
});

test('el padron real se carga completo', function () {
    (new PersonasSeeder)->run();

    expect(Persona::count())->toBe(218);

    $arturo = Persona::where('curp', 'PAPA651220HYNTCR02')->sole();
    expect($arturo->nombre)->toBe('JOSE ARTURO')
        ->and($arturo->apellido)->toBe('PAT PECH')
        ->and($arturo->imss)->toBe('6846503065');

    // Los apellidos con partícula no se parten mal.
    expect(Persona::where('curp', 'LARM700107MYNLDR02')->value('apellido'))->toBe('DE LLANO RODRIGUEZ');

    // Nadie se queda sin apellido (la columna no lo acepta).
    expect(Persona::whereNull('apellido')->orWhere('apellido', '')->count())->toBe(0);
});

test('correrlo dos veces no duplica', function () {
    $filas = [
        ['nombre' => 'Juan', 'apellido' => 'Perez', 'curp' => 'PEJU900101HDFRRN01'],
        ['nombre' => 'Pedro', 'apellido' => 'Lopez', 'curp' => 'LOPE900101HDFRRN02'],
    ];

    sembrarPersonas($filas);
    sembrarPersonas($filas);

    expect(Persona::count())->toBe(2);
});
