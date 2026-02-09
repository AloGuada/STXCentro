<?php

namespace Database\Factories\Sti;

use App\Models\Sti\ItemTipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\ItemTipo>
 */
class ItemTipoFactory extends Factory
{
    protected $model = ItemTipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->randomElement([
                'RAM', 'HDD', 'SSD', 'Procesador', 'Tarjeta Madre',
                'Fuente de Poder', 'Tarjeta de Video', 'Monitor',
                'Teclado', 'Mouse', 'Cable de Red', 'Ventilador',
            ]),
        ];
    }
}
