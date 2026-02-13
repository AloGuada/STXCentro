<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z]{3}[0-9]{4}'),
            'razon_social' => fake()->company(),
            'nombre_comercial' => fake()->optional()->company(),
            'rfc' => fake()->unique()->regexify('[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}'),
            'direccion' => fake()->optional()->address(),
            'telefono' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'contacto_nombre' => fake()->optional()->name(),
            'tiene_acceso_portal' => fake()->boolean(20),
            'maneja_credito' => fake()->boolean(30),
            'limite_credito' => fake()->randomFloat(2, 0, 500000),
            'dias_credito_default' => fake()->randomElement([0, 15, 30, 45, 60, 90]),
            'departamento_id' => Departamento::factory(),
            'tipo_proveedor' => fake()->randomElement(['materiales', 'servicios', 'equipos', 'mixto']),
            'activo' => true,
        ];
    }
}
