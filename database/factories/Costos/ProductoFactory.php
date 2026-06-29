<?php

namespace Database\Factories\Costos;

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
}
