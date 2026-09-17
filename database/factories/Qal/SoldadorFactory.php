<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Soldador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Soldador>
 */
class SoldadorFactory extends Factory
{
    protected $model = Soldador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'clave' => mb_strtoupper(fake()->unique()->lexify('???')),
            'certificacion' => fake()->bothify('??-####'),
            'certificacion_vence_at' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /** Para probar el aviso: soldó con la certificación caducada. */
    public function certificacionVencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificacion_vence_at' => fake()->dateTimeBetween('-2 years', '-1 day'),
        ]);
    }
}
