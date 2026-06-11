<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFactorOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraFactorOverride>
 */
class ObraFactorOverrideFactory extends Factory
{
    protected $model = ObraFactorOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'factor_id' => Factor::factory(),
            'formula' => 'kg_fab * 2',
        ];
    }
}
