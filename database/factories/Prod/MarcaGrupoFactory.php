<?php

namespace Database\Factories\Prod;

use App\Models\Pieza;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\MarcaGrupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\MarcaGrupo>
 */
class MarcaGrupoFactory extends Factory
{
    protected $model = MarcaGrupo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pieza_id' => Pieza::factory(),
            'grupo_precio_id' => GrupoPrecio::factory(),
        ];
    }
}
