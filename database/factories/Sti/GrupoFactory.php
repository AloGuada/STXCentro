<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Grupo>
 */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipo_id' => Equipo::factory(),
            'item_id' => Item::factory()->instalado(),
        ];
    }
}
