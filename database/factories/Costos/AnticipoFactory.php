<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Anticipo;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Anticipo>
 */
class AnticipoFactory extends Factory
{
    protected $model = Anticipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monto = fake()->randomFloat(2, 1000, 100000);

        return [
            'proveedor_id' => Proveedor::factory(),
            'monto' => $monto,
            'saldo_disponible' => $monto,
            'moneda' => 'mxn',
            'estatus' => 'vigente',
            'fecha' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'referencia' => fake()->optional()->bothify('REF-####'),
        ];
    }

    public function agotado(): static
    {
        return $this->state(fn () => [
            'saldo_disponible' => 0,
            'estatus' => 'agotado',
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn () => ['estatus' => 'cancelado']);
    }
}
