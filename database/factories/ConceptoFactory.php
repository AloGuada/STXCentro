<?php

namespace Database\Factories;

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Concepto>
 */
class ConceptoFactory extends Factory
{
    protected $model = Concepto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            // Reutiliza el catálogo v1 de la obra para que varias piezas de la
            // misma obra caigan en el mismo catálogo vigente.
            'catalogo_id' => fn (array $attributes) => Catalogo::query()->firstOrCreate(
                ['obra_id' => $attributes['obra_id'], 'version' => 1],
                ['nombre' => 'Catálogo de prueba', 'vigente' => true],
            )->id,
            'marca' => fake()->unique()->regexify('[A-Z]{2}-[0-9]{3}'),
            'descripcion' => fake()->words(3, true),
            'cantidad' => fake()->numberBetween(10, 500),
            'peso_unitario' => fake()->randomFloat(3, 5, 500),
            'longitud' => fake()->numberBetween(500, 12000),
            'categoria_id' => Categoria::factory(),
            'version' => 1,
            'activo' => true,
        ];
    }
}
