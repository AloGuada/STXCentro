<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Factura>
 */
class FacturaFactory extends Factory
{
    protected $model = Factura::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 50000);
        $iva = round($subtotal * 0.16, 2);

        return [
            'orden_compra_id' => OrdenCompra::factory(),
            'proveedor_id' => Proveedor::factory(),
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $subtotal + $iva,
            'moneda' => 'mxn',
            'fecha_factura' => fake()->optional()->date(),
            'estatus' => 'pendiente_aprobacion',
        ];
    }

    public function pendienteAprobacion(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_aprobacion']);
    }

    public function pendientePago(): static
    {
        return $this->state(fn () => [
            'estatus' => 'pendiente_pago',
            'aprobada_costos' => true,
            'aprobada_costos_at' => now(),
        ]);
    }

    public function pagada(): static
    {
        return $this->state(fn () => [
            'estatus' => 'pagada',
            'aprobada_costos' => true,
            'aprobada_costos_at' => now(),
            'aceptada_contabilidad' => true,
            'aceptada_contabilidad_at' => now(),
        ]);
    }
}
