<?php

namespace Database\Factories\Costos;

use App\Models\Costos\OrdenCompra;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\OrdenCompra>
 */
class OrdenCompraFactory extends Factory
{
    protected $model = OrdenCompra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'obra_id' => null,
            'departamento_id' => Departamento::factory(),
            'creado_por' => User::factory(),
            'moneda' => 'mxn',
            'total' => fake()->randomFloat(2, 1000, 100000),
            'fecha_entrega_esperada' => fake()->dateTimeBetween('+7 days', '+60 days'),
            'estatus' => 'pendiente_factura',
        ];
    }

    public function pendienteEntrega(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_entrega']);
    }

    public function pendienteFactura(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_factura']);
    }

    public function pendienteAprobacion(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_aprobacion']);
    }

    public function pendientePago(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_pago']);
    }

    public function pagada(): static
    {
        return $this->state(fn () => ['estatus' => 'pagada']);
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['estatus' => 'cancelada']);
    }
}
