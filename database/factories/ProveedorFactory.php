<?php

namespace Database\Factories;

use App\Enums\FormaPago;
use App\Enums\ProveedorEstatus;
use App\Enums\TipoProveedor;
use App\Models\Banco;
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
            'tipo_proveedor' => TipoProveedor::Proveedor->value,
            'forma_pago' => FormaPago::Transferencia->value,
            'tipo_persona' => fake()->randomElement(['fisica', 'moral']),
            'codigo_postal' => fake()->numerify('#####'),
            'banco_id' => Banco::factory(),
            'titular_cuenta' => fn (array $attrs) => $attrs['razon_social'],
            'clabe' => fake()->numerify('##################'),
            'moneda_cuenta' => 'MXN',
            'activo' => true,
            'estatus' => ProveedorEstatus::Activo->value,
        ];
    }

    public function tercero(): static
    {
        return $this->state(fn () => [
            'tipo_proveedor' => TipoProveedor::Tercero->value,
            'forma_pago' => FormaPago::Transferencia->value,
        ]);
    }

    public function servicio(): static
    {
        return $this->state(fn () => [
            'tipo_proveedor' => TipoProveedor::Servicio->value,
            'forma_pago' => null,
            'banco_id' => null,
            'clabe' => null,
            'titular_cuenta' => null,
            'numero_servicio' => fake()->numerify('##########'),
            'referencia_servicio' => fake()->numerify('####'),
        ]);
    }

    public function pendienteValidacion(): static
    {
        return $this->state(fn () => [
            'activo' => false,
            'estatus' => ProveedorEstatus::PendienteValidacion->value,
        ]);
    }

    public function rechazado(): static
    {
        return $this->state(fn () => [
            'activo' => false,
            'estatus' => ProveedorEstatus::Rechazado->value,
        ]);
    }
}
