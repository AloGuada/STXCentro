<?php

namespace Database\Factories;

use App\Models\BadgeConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BadgeConfig>
 */
class BadgeConfigFactory extends Factory
{
    protected $model = BadgeConfig::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->sentence(3),
            'tabla' => 'costos_facturas',
            'campo_estatus' => 'estatus',
            'operador' => '=',
            'valor_estatus' => 'pendiente_entrega',
            'condiciones_extra' => null,
            'rol' => 'almacen',
            'nav_href' => '/admin/costos/facturas',
            'filter_href' => '/admin/costos/facturas?estatus=pendiente_entrega',
            'activo' => true,
        ];
    }
}
