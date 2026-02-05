<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Tecnico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Mantenimiento>
 */
class MantenimientoFactory extends Factory
{
    protected $model = Mantenimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipo_id' => Equipo::factory(),
            'tipo' => fake()->randomElement(['preventivo', 'correctivo', 'predictivo']),
            'fecha_programada' => fake()->dateTimeBetween('now', '+3 months'),
            'descripcion' => fake()->sentence(),
            'tecnico_id' => Tecnico::factory(),
        ];
    }

    public function preventivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'preventivo',
        ]);
    }

    public function correctivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'correctivo',
        ]);
    }
}
