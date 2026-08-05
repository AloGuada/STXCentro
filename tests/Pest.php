<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // Tasas de cambio deterministas: las pruebas de presupuesto en divisa
        // no deben pegar a Banxico/ECB por red. TipoCambioServiceTest re-liga el
        // servicio real en su propio beforeEach para probar la integración.
        $this->app->bind(\App\Services\Costos\TipoCambioService::class, fn () => new class extends \App\Services\Costos\TipoCambioService
        {
            public function mxnPorUnidad(string $moneda, ?\Carbon\CarbonInterface $fecha = null): float
            {
                return match (strtolower($moneda)) {
                    'usd' => 18.5,
                    'eur' => 20.0,
                    default => 1.0,
                };
            }
        });
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Otorga al usuario los permisos CRUD de solicitudes de pago (costos).
 * Útil en tests que actúan sobre rutas admin.costos.solicitudes-pago.*
 */
function darPermisosSolicitudesPago(\App\Models\User $user): \App\Models\User
{
    $permisos = [
        'costos.solicitudes-pago.ver',
        'costos.solicitudes-pago.crear',
        'costos.solicitudes-pago.editar',
        'costos.solicitudes-pago.eliminar',
    ];

    foreach ($permisos as $name) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permisos);

    return $user;
}

/**
 * Una marca del catálogo con sus piezas físicas (QS), que es como llega el
 * layout: un renglón por pieza repitiendo el modelo.
 *
 * @param  array<string, mixed>  $atributos  de la marca
 */
function marcaConPiezas(int $piezas = 1, array $atributos = []): \App\Models\Concepto
{
    $marca = \App\Models\Concepto::factory()->create([...$atributos, 'cantidad' => $atributos['cantidad'] ?? $piezas]);

    \App\Models\Prod\Pieza::factory()->count($piezas)->create([
        'concepto_id' => $marca->id,
        'catalogo_id' => $marca->catalogo_id,
    ]);

    return $marca->load('piezas');
}

/**
 * El proceso sembrado por la migración. Los tests casi siempre quieren
 * soldadura; pintura sirve para probar que los topes no se mezclan.
 */
function proceso(string $nombre = 'Soldadura'): \App\Models\Prod\Proceso
{
    return \App\Models\Prod\Proceso::where('nombre', $nombre)->firstOrFail();
}

/**
 * Marca la obra como pagadora de esos procesos. Sin esto la captura los rechaza,
 * que es justo lo que se quiere en producción pero estorba al montar un test.
 */
function obraPagaProcesos(int $obraId, \App\Models\Prod\Proceso ...$procesos): void
{
    $procesos = $procesos ?: [proceso()];

    \App\Models\Obra::findOrFail($obraId)->procesos()->syncWithoutDetaching(
        collect($procesos)->pluck('id')->all()
    );
}

/**
 * Grupo de precios de la obra con tarifa para un proceso, ya asignado a la
 * marca. Es el mínimo que necesita una liquidación para no pagar en cero.
 */
function tarifaDeMarca(
    \App\Models\Concepto $marca,
    float $precioKilo,
    ?\App\Models\Prod\Proceso $proceso = null,
    ?\App\Models\Prod\GrupoPrecio $grupoPrecio = null,
): \App\Models\Prod\GrupoPrecio {
    $grupoPrecio ??= \App\Models\Prod\GrupoPrecio::factory()->create(['obra_id' => $marca->obra_id]);

    \App\Models\Prod\GrupoPrecioProceso::updateOrCreate(
        ['grupo_precio_id' => $grupoPrecio->id, 'proceso_id' => ($proceso ?? proceso())->id],
        ['precio_kilo' => $precioKilo],
    );

    \App\Models\Prod\GrupoPrecioConcepto::firstOrCreate([
        'grupo_precio_id' => $grupoPrecio->id,
        'concepto_id' => $marca->id,
    ]);

    return $grupoPrecio->load('precios');
}

/**
 * Captura producción de varias piezas de golpe: un renglón por QS, que es como
 * queda el destajo desde que se paga pieza por pieza.
 *
 * @param  iterable<\App\Models\Prod\Pieza>  $piezas
 */
function capturarPiezas(
    iterable $piezas,
    \App\Models\Prod\GrupoTrabajo $grupo,
    string $fecha,
    float $porcentaje = 100,
    ?\App\Models\Prod\Proceso $proceso = null,
): void {
    $proceso ??= proceso();

    foreach ($piezas as $pieza) {
        \App\Models\Prod\Registro::create([
            'fecha' => $fecha,
            'pieza_id' => $pieza->id,
            'proceso_id' => $proceso->id,
            'grupo_trabajo_id' => $grupo->id,
            'porcentaje' => $porcentaje,
        ]);
    }
}

function darPermisoVerTodasSolicitudes(\App\Models\User $user): \App\Models\User
{
    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'costos.solicitudes-pago.ver-todas',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('costos.solicitudes-pago.ver-todas');

    return $user;
}
