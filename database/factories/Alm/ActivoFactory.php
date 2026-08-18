<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba. En producción la pieza nace por
 * `RegistradorPiezas`, que además emite su movimiento al kardex: creada con la
 * factory, la existencia queda en cero y el invariante no se sostiene.
 *
 * @extends Factory<Activo>
 */
class ActivoFactory extends Factory
{
    protected $model = Activo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory()->porPieza(),
            'no_serie' => strtoupper(fake()->unique()->bothify('SER-####')),
            'codigo_barras' => null,
            'marca' => fake()->randomElement(['DeWalt', 'Makita', 'Truper']),
            'modelo' => strtoupper(fake()->bothify('MDL-###')),
            'id_mantenimiento' => null,
            'almacen_id' => Almacen::factory(),
            'ubicacion_id' => null,
            'costo' => fake()->randomFloat(2, 500, 3000),
            'estatus' => ActivoEstatus::Disponible,
            'condicion' => 'Buena',
        ];
    }

    public function de(Producto $producto, Almacen $almacen): static
    {
        return $this->state(fn (): array => [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
        ]);
    }

    public function prestado(): static
    {
        return $this->state(fn (): array => ['estatus' => ActivoEstatus::Prestado]);
    }

    public function enReparacion(): static
    {
        return $this->state(fn (): array => ['estatus' => ActivoEstatus::EnReparacion]);
    }

    public function dadoDeBaja(): static
    {
        return $this->state(fn (): array => ['estatus' => ActivoEstatus::Baja]);
    }
}
