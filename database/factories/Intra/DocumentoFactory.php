<?php

namespace Database\Factories\Intra;

use App\Enums\TipoDocumento;
use App\Models\Intra\Area;
use App\Models\Intra\Documento;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Intra\Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'media_id' => Media::factory(),
            'descripcion' => fake()->words(4, true),
            'codigo' => strtoupper(fake()->lexify('PG-STX-??-??-??')),
            'tipo' => fake()->randomElement(TipoDocumento::cases()),
            'order' => fake()->numberBetween(0, 100),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function forArea(Area $area): static
    {
        return $this->state(fn (array $attributes) => [
            'area_id' => $area->id,
        ]);
    }

    public function withMedia(Media $media): static
    {
        return $this->state(fn (array $attributes) => [
            'media_id' => $media->id,
        ]);
    }

    public function ofType(TipoDocumento $tipo): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => $tipo,
        ]);
    }
}
