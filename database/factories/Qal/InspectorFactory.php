<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Inspector>
 */
class InspectorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'fase' => $this->faker->randomElement(FaseTransformacion::cases()),
        ];
    }
}
