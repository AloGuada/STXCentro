<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ajuste>
 */
class AjusteFactory extends Factory
{
    protected $model = Ajuste::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'motivo' => AjusteMotivo::ErrorCaptura,
            'autorizado_por' => Usuario::factory(),
            'fecha' => now()->toDateString(),
            'observaciones' => null,
        ];
    }

    public function de(Almacen $almacen): static
    {
        return $this->state(fn (): array => ['almacen_id' => $almacen->id]);
    }
}
