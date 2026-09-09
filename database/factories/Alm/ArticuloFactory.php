<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Articulo>
 */
class ArticuloFactory extends Factory
{
    protected $model = Articulo::class;

    /**
     * Nace ligado a un producto, que es el caso normal: casi todo el material
     * llega por una orden de compra y trae su identidad puesta. El suelto es la
     * excepción y se pide con `sinLigar()`.
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'codigo' => strtoupper($this->faker->unique()->bothify('ART-####')),
            'descripcion' => $this->faker->words(3, true),
            'unidad' => $this->faker->randomElement(['PZA', 'KG', 'LTS', 'MTS']),
            'tipo' => ProductoTipo::Insumo,
            'clasificacion_abc' => ClasificacionAbc::C,
            'activo' => true,
            'creado_por' => null,
        ];
    }

    /** Material real sin identidad de compra: el que abre un almacén. */
    public function sinLigar(): static
    {
        return $this->state(fn (): array => ['producto_id' => null]);
    }

    /** Lleva número de serie y resguardo por persona: cada pieza se identifica. */
    public function porPieza(): static
    {
        return $this->state(fn (): array => [
            'tipo' => ProductoTipo::Activo,
            'se_controla_por_pieza' => true,
        ]);
    }

    /** Activo sin serie: sale y regresa, pero se lleva como un solo renglón por cantidad. */
    public function activoPorCantidad(): static
    {
        return $this->state(fn (): array => [
            'tipo' => ProductoTipo::Activo,
            'se_controla_por_pieza' => false,
            'unidad' => 'PZA',
        ]);
    }
}
