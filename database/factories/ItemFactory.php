<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->bothify('ART-####')),
            'descripcion' => $this->faker->unique()->words(3, true),
            'unidad' => 'PZA',
            'activo' => true,
            'creado_por' => null,
        ];
    }
}
