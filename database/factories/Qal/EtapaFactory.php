<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Etapa;
use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Etapa>
 */
class EtapaFactory extends Factory
{
    protected $model = Etapa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->sentence(3),
            'obra_id' => Obra::factory(),
        ];
    }
}
