<?php

namespace Database\Factories\Alm;

use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba. En producción la existencia nace
 * del ledger: nadie la crea con un saldo ya puesto.
 *
 * @extends Factory<Existencia>
 */
class ExistenciaFactory extends Factory
{
    protected $model = Existencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => 0,
            'costo_promedio' => 0,
            'valor' => 0,
            'ubicacion_id' => null,
        ];
    }

    /**
     * Saldo inicial sin pasar por el ledger. Úsalo sólo cuando la prueba no
     * trate sobre cómo llegó el material: si trata de eso, siembra con un ajuste.
     */
    public function conSaldo(float $cantidad, float $costoPromedio = 0): static
    {
        return $this->state(fn (): array => [
            'cantidad' => $cantidad,
            'costo_promedio' => $costoPromedio,
            'valor' => $cantidad * $costoPromedio,
        ]);
    }
}
