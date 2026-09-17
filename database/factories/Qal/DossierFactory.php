<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\EstatusDossier;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Dossier>
 */
class DossierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'plantilla_id' => null,
            'plantilla_nombre' => 'Estándar',
            'estatus' => EstatusDossier::Borrador,
        ];
    }

    public function entregado(): static
    {
        return $this->state(fn (): array => ['estatus' => EstatusDossier::Entregado, 'entregado_at' => now()]);
    }
}
