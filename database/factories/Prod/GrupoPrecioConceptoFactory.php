<?php

namespace Database\Factories\Prod;

use App\Models\Concepto;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\GrupoPrecioConcepto>
 */
class GrupoPrecioConceptoFactory extends Factory
{
    protected $model = GrupoPrecioConcepto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_precio_id' => GrupoPrecio::factory(),
            'concepto_id' => Concepto::factory(),
        ];
    }
}
