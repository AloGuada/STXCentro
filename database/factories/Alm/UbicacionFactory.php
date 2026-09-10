<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\UbicacionTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ubicacion>
 */
class UbicacionFactory extends Factory
{
    protected $model = Ubicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'padre_id' => null,
            'codigo' => strtoupper(fake()->unique()->bothify('??-##')),
            'nombre' => ucfirst(fake()->words(2, true)),
            'tipo' => UbicacionTipo::Rack,
            'activa' => true,
        ];
    }

    public function de(Almacen $almacen): static
    {
        return $this->state(fn (): array => ['almacen_id' => $almacen->id]);
    }

    public function bajo(Ubicacion $padre): static
    {
        return $this->state(fn (): array => [
            'almacen_id' => $padre->almacen_id,
            'padre_id' => $padre->id,
            'tipo' => UbicacionTipo::Nivel,
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activa' => false]);
    }
}
