<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->sentence(),
            'path' => 'media/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(10000, 5000000),
            'mediable_type' => null,
            'mediable_id' => null,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime' => 'application/pdf',
            'path' => 'media/'.fake()->uuid().'.pdf',
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime' => 'image/jpeg',
            'path' => 'media/'.fake()->uuid().'.jpg',
        ]);
    }
}
