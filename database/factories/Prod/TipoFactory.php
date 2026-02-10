<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Tipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Tipo>
 */
class TipoFactory extends Factory
{
    protected $model = Tipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->randomElement(['Horas Extra', 'Bono Productividad', 'Transporte', 'Alimentacion']),
            'orden' => 0,
            'desgloce' => false,
        ];
    }
}
