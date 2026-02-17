<?php

namespace Database\Factories\Costos;

use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\AfectacionPresupuestal>
 */
class AfectacionPresupuestalFactory extends Factory
{
    protected $model = AfectacionPresupuestal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->date(),
            'tipo_origen' => fake()->randomElement(['nomina', 'gasto_directo', 'reembolso', 'ajuste_presupuestal', 'otro']),
            'descripcion' => fake()->sentence(),
            'monto_total' => fake()->randomFloat(2, 100, 50000),
            'estatus' => 'borrador',
            'proveedor_id' => Proveedor::factory(),
            'departamento_id' => Departamento::factory(),
            'creado_por' => User::factory(),
        ];
    }

    public function pendienteFirma(): static
    {
        return $this->state(fn () => ['estatus' => 'pendiente_firma']);
    }

    public function aprobada(): static
    {
        return $this->state(fn () => ['estatus' => 'aprobada']);
    }
}
