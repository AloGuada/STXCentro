<?php

namespace Database\Factories\Cal;

use App\Models\Cal\Etapa;
use App\Models\Cal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\Etapa>
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
