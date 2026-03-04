<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'tipo' => fake()->randomElement(['hard', 'soft']),
        ];
    }
}
