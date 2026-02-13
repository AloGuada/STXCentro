<?php

namespace Database\Factories\Costos;

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\AprobacionDepartamento>
 */
class AprobacionDepartamentoFactory extends Factory
{
    protected $model = AprobacionDepartamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'departamento_id' => Departamento::factory(),
            'nivel' => fake()->numberBetween(1, 5),
            'nombre_nivel' => fake()->randomElement(['Jefe Depto', 'Gerente', 'Director', 'Contralor']),
            'aprobador_id' => User::factory(),
            'activo' => true,
        ];
    }
}
