<?php

namespace Database\Factories\Intra;

use App\Models\Intra\SeccionEstatica;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Intra\SeccionEstatica>
 */
class SeccionEstaticaFactory extends Factory
{
    protected $model = SeccionEstatica::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titulo = fake()->words(3, true);

        return [
            'slug' => Str::slug($titulo).'-'.fake()->unique()->randomNumber(4),
            'titulo' => $titulo,
            'descripcion' => fake()->optional()->sentence(),
            'boton' => fake()->words(2, true),
            'order' => fake()->numberBetween(0, 100),
            'activo' => true,
        ];
    }

    public function withUrlExterna(string $url = 'https://example.com/document'): static
    {
        return $this->state(fn (array $attributes) => [
            'url_externa' => $url,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
