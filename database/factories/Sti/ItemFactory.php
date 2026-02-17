<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Item;
use App\Models\Sti\ItemTipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->words(3, true),
            'tipo_id' => ItemTipo::factory(),
            'costo' => fake()->randomFloat(2, 50, 5000),
            'no_serie' => fake()->unique()->regexify('[A-Z]{2}[0-9]{8}'),
            'estado' => 'disponible',
            'principal' => false,
            'accesorio' => false,
        ];
    }

    public function instalado(): static
    {
        return $this->state(fn () => ['estado' => 'instalado']);
    }

    public function dañado(): static
    {
        return $this->state(fn () => ['estado' => 'dañado']);
    }

    public function baja(): static
    {
        return $this->state(fn () => ['estado' => 'baja']);
    }
}
