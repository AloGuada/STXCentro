<?php

namespace Database\Factories\Prod;

use App\Models\Obra;
use App\Models\Prod\Catalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Catalogo>
 */
class CatalogoFactory extends Factory
{
    protected $model = Catalogo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'nombre' => 'Catálogo '.fake()->unique()->regexify('[A-Z]{3}'),
            'version' => 1,
            'vigente' => true,
            'notas' => null,
        ];
    }

    public function historico(): static
    {
        return $this->state(fn () => ['vigente' => false]);
    }
}
