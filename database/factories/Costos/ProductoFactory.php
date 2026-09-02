<?php

namespace Database\Factories\Costos;

use App\Enums\Alm\ProductoTipo;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->bothify('PRD-####')),
            'descripcion' => $this->faker->words(3, true),
            'unidad' => $this->faker->randomElement(['pza', 'kg', 'm', 'lt', 'caja']),
            'activo' => true,
            'creado_por' => null,
        ];
    }

    /** Un servicio o un gasto: se compra pero no se guarda, así que no lleva kardex. */
    public function sinInventario(): static
    {
        return $this->state(fn (): array => ['controla_inventario' => false]);
    }

    /** Lo tecleado al vuelo por Compras: sin código, nadie lo ha clasificado. */
    public function sinClasificar(): static
    {
        return $this->state(fn (): array => [
            'codigo' => null,
            'controla_inventario' => false,
        ]);
    }

    /** Lleva número de serie y resguardo por persona: cada pieza se identifica. */
    public function porPieza(): static
    {
        return $this->state(fn (): array => [
            'tipo' => ProductoTipo::Activo,
            'se_controla_por_pieza' => true,
        ]);
    }
}
