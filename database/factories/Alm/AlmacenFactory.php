<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\AlmacenTipo;
use App\Models\Alm\Almacen;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alm\Almacen>
 */
class AlmacenFactory extends Factory
{
    protected $model = Almacen::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => strtoupper(fake()->unique()->lexify('??')),
            'nombre' => 'Almacén '.fake()->word(),
            'obra_id' => null,
            'tipo' => AlmacenTipo::Insumos,
            'responsable_id' => null,
            'observaciones' => null,
            'activo' => true,
        ];
    }

    /** Almacén de obra: el de montaje que vive dentro de un edificio. */
    public function deObra(?Obra $obra = null): self
    {
        return $this->state(fn (): array => [
            'obra_id' => $obra?->id ?? Obra::factory(),
            'tipo' => AlmacenTipo::Montaje,
        ]);
    }
}
