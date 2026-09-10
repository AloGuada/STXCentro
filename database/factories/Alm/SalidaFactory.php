<?php

namespace Database\Factories\Alm;

use App\Models\Alm\Almacen;
use App\Models\Alm\Salida;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba. En producción la salida nace por
 * `RegistradorSalida`, que además descuenta el kardex.
 *
 * @extends Factory<Salida>
 */
class SalidaFactory extends Factory
{
    protected $model = Salida::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'entregado_por' => Usuario::factory(),
            'recibe_nombre' => fake()->name(),
            'fecha' => now()->toDateString(),
        ];
    }

    public function de(Almacen $almacen): static
    {
        return $this->state(fn (): array => ['almacen_id' => $almacen->id]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (): array => [
            'cancelada_at' => now(),
            'motivo_cancelacion' => 'Error de captura',
        ]);
    }
}
