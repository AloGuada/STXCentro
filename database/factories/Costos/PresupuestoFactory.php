<?php

namespace Database\Factories\Costos;

use App\Enums\Costos\PresupuestoEstatus;
use App\Models\Cob\Partida;
use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Presupuesto>
 */
class PresupuestoFactory extends Factory
{
    protected $model = Presupuesto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuestable_type' => Obra::class,
            'presupuestable_id' => Obra::factory(),
            'nombre_interno' => null,
            'estatus' => PresupuestoEstatus::Activo,
        ];
    }

    public function paraObra(?Obra $obra = null): static
    {
        return $this->state(fn () => [
            'presupuestable_type' => Obra::class,
            'presupuestable_id' => $obra?->id ?? Obra::factory(),
        ]);
    }

    public function paraProyecto(?Proyecto $proyecto = null): static
    {
        return $this->state(fn () => [
            'presupuestable_type' => Proyecto::class,
            'presupuestable_id' => $proyecto?->id ?? Proyecto::factory(),
        ]);
    }

    public function paraPartida(?Partida $partida = null): static
    {
        return $this->state(fn () => [
            'presupuestable_type' => Partida::class,
            'presupuestable_id' => $partida?->id ?? Partida::factory(),
        ]);
    }

    public function cerrado(): static
    {
        return $this->state(fn () => ['estatus' => PresupuestoEstatus::Cerrado]);
    }
}
