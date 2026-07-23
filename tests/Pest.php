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
