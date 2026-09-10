<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\ConteoEstatus;
use App\Enums\Alm\ConteoOrigen;
use App\Models\Alm\Almacen;
use App\Models\Alm\Conteo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conteo>
 */
class ConteoFactory extends Factory
{
    protected $model = Conteo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'origen' => ConteoOrigen::Programado,
            'fecha_programada' => today()->toDateString(),
            'estatus' => ConteoEstatus::Pendiente,
            'responsable_id' => null,
            'creado_por' => null,
        ];
    }

    public function de(Almacen $almacen): static
    {
        return $this->state(fn (): array => ['almacen_id' => $almacen->id]);
    }

    public function vencido(): static
    {
        return $this->state(fn (): array => ['fecha_programada' => today()->subDays(3)->toDateString()]);
    }
}
