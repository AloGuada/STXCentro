<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use App\Models\Cotiz\ResumenBloqueColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ResumenBloqueColor>
 */
class ResumenBloqueColorFactory extends Factory
{
    protected $model = ResumenBloqueColor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bloque' => fake()->randomElement(ResumenBloque::cases()),
            'color' => fake()->hexColor(),
        ];
    }
}
