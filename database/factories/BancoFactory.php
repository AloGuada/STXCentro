<?php

namespace Database\Factories;

use App\Models\Banco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Banco>
 */
class BancoFactory extends Factory
{
    protected $model = Banco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'digitos_cuenta' => null,
            'es_pagador' => false,
            'activo' => true,
        ];
    }

    public function pagador(int $digitos = 10): static
    {
        return $this->state(fn () => [
            'es_pagador' => true,
            'digitos_cuenta' => $digitos,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
