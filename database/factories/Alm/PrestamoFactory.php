<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\PrestamoEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Prestamo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba: en producción el resguardo
 * nace del `RegistradorPrestamos`, que es quien marca lo prestado.
 *
 * @extends Factory<Prestamo>
 */
class PrestamoFactory extends Factory
{
    protected $model = Prestamo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'responsable_id' => Usuario::factory(),
            'fecha_salida' => today()->toDateString(),
            'fecha_retorno_esperada' => today()->addWeek()->toDateString(),
            'estatus' => PrestamoEstatus::Abierto,
        ];
    }
}
