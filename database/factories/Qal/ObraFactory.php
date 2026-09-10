<?php

namespace Database\Factories\Qal;

use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Obra>
 */
class ObraFactory extends Factory
{
    protected $model = Obra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => ObraDelPortal::factory()->state(['activa' => true]),
        ];
    }

    /**
     * Cuelga de una obra del portal con ese número.
     */
    public function conNumero(string $no): static
    {
        return $this->state(fn (): array => [
            'obra_id' => ObraDelPortal::factory()->state(['no' => $no, 'activa' => true]),
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => [
            'obra_id' => ObraDelPortal::factory()->state(['activa' => false]),
        ]);
    }
}
