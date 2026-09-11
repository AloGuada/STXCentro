<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\OrigenFirmante;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Firmante>
 */
class FirmanteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden' => $this->faker->numberBetween(3, 9),
            'etiqueta' => 'Aprobó',
            'cargo' => ucfirst($this->faker->words(2, true)),
            'origen' => OrigenFirmante::Usuario,
            'usuario_id' => null,
        ];
    }

    public function creador(): static
    {
        return $this->state(fn (): array => ['origen' => OrigenFirmante::Creador, 'usuario_id' => null]);
    }

    public function de(Usuario $usuario): static
    {
        return $this->state(fn (): array => ['origen' => OrigenFirmante::Usuario, 'usuario_id' => $usuario->id]);
    }
}
