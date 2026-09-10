<?php

namespace Database\Factories\Qal;

use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\LoteAccesorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\LoteAccesorio>
 */
class LoteAccesorioFactory extends Factory
{
    protected $model = LoteAccesorio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => ObraDelPortal::factory(),
            'marca' => 'ACC-'.fake()->unique()->numerify('PL###'),
            'descripcion' => 'Placa de conexión',
            'total_unidades' => 500,
            'kg_unitario' => 4.5,
            'elementos_unitarios' => 2,
        ];
    }
}
