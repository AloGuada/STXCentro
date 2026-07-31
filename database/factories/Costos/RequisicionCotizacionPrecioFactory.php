<?php

namespace Database\Factories\Costos;

use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\RequisicionCotizacionPrecio>
 */
class RequisicionCotizacionPrecioFactory extends Factory
{
    protected $model = RequisicionCotizacionPrecio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicion_detalle_id' => RequisicionDetalle::factory(),
            'proveedor_id' => Proveedor::factory(),
            // Toda cotización real cuelga de una opción (su columna en el
            // comparativo); sin ella la celda no se dibuja. Se reutiliza la del
            // proveedor en esa requisición o se crea. Si quien llama manda
            // `opcion_id` explícito (incluido null), este closure no corre.
            'opcion_id' => fn (array $atributos) => $this->opcionPara($atributos),
            'precio_unitario' => fake()->randomFloat(2, 10, 5000),
            'moneda' => 'mxn',
            'tiempo_entrega_dias' => fake()->optional()->numberBetween(1, 30),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    /** Cotización huérfana: sin columna en el comparativo (el bug de duplicar). */
    public function sinOpcion(): static
    {
        return $this->state(['opcion_id' => null]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function opcionPara(array $atributos): ?int
    {
        $requisicionId = RequisicionDetalle::whereKey($atributos['requisicion_detalle_id'])
            ->value('requisicion_id');

        if ($requisicionId === null) {
            return null;
        }

        return RequisicionCotizacionOpcion::firstOrCreate(
            ['requisicion_id' => $requisicionId, 'proveedor_id' => $atributos['proveedor_id']],
            ['orden' => (int) RequisicionCotizacionOpcion::where('requisicion_id', $requisicionId)->max('orden') + 1],
        )->id;
    }
}
