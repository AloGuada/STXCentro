<?php

namespace Database\Factories\Alm;

use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba. En producción la partición nace
 * del ledger, en la misma transacción que mueve el saldo: nadie la crea con una
 * cantidad ya puesta.
 *
 * Ojo al usarla: no valida el invariante `SUM(asignaciones) <= existencia.cantidad`.
 * Si la prueba trata sobre el invariante, siembra con el ledger.
 *
 * @extends Factory<Asignacion>
 */
class AsignacionFactory extends Factory
{
    protected $model = Asignacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'existencia_id' => Existencia::factory(),
            'obra_id' => Obra::factory(),
            'cantidad' => 0,
        ];
    }

    public function de(float $cantidad): static
    {
        return $this->state(fn (): array => ['cantidad' => $cantidad]);
    }
}
