<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Modelo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Modelo>
 */
class ModeloFactory extends Factory
{
    protected $model = Modelo::class;

    /**
     * Una versión ya convertida.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => ObraDelPortal::factory(),
            'version' => 1,
            'nombre_original' => 'NAVE-PRODUCCION.ifc',
            'tamano_bytes' => 1024,
            'estatus' => EstatusModelo::Listo,
            'procesado_at' => now(),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (): array => ['estatus' => EstatusModelo::Pendiente, 'procesado_at' => null]);
    }
}
