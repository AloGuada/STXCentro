<?php

namespace Database\Factories\Costos;

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
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
            'permiso_id' => Permiso::factory(),
            'aprobador_id' => User::factory(),
        ];
    }
}
