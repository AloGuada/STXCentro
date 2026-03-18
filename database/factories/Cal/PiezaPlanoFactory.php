<?php

namespace Database\Factories\Cal;

use App\Models\Cal\Pieza;
use App\Models\Cal\PiezaPlano;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\PiezaPlano>
 */
class PiezaPlanoFactory extends Factory
{
    protected $model = PiezaPlano::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pieza_id' => Pieza::factory(),
            'pdf_path' => null,
            'plano_normal' => null,
            'dwg_path' => null,
            'version' => 1,
        ];
    }
}
