<?php

namespace Database\Seeders;

use App\Models\BadgeConfig;
use Illuminate\Database\Seeder;

class BadgeConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'nombre' => 'Facturas sin entrega',
                'tabla' => 'costos_facturas',
                'campo_estatus' => 'estatus',
                'operador' => '=',
                'valor_estatus' => 'pendiente_entrega',
                'condiciones_extra' => null,
                'rol' => 'almacen',
                'nav_href' => '/admin/costos/facturas',
                'filter_href' => '/admin/costos/facturas?estatus=pendiente_entrega',
            ],
            [
                'nombre' => 'Facturas pendientes aprobación',
                'tabla' => 'costos_facturas',
                'campo_estatus' => 'estatus',
                'operador' => '=',
                'valor_estatus' => 'pendiente_aprobacion',
                'condiciones_extra' => null,
                'rol' => 'costos',
                'nav_href' => '/admin/costos/facturas',
                'filter_href' => '/admin/costos/facturas?estatus=pendiente_aprobacion',
            ],
            [
                'nombre' => 'Facturas pendientes contabilidad',
                'tabla' => 'costos_facturas',
                'campo_estatus' => 'estatus',
                'operador' => '=',
                'valor_estatus' => 'pendiente_pago',
                'condiciones_extra' => [
                    ['campo' => 'aprobada_costos', 'operador' => '=', 'valor' => true],
                    ['campo' => 'aceptada_contabilidad', 'operador' => '=', 'valor' => false],
                ],
                'rol' => 'contabilidad',
                'nav_href' => '/admin/costos/facturas',
                'filter_href' => '/admin/costos/facturas?estatus=pendiente_pago',
            ],
            [
                'nombre' => 'Entregas recientes (7 días)',
                'tabla' => 'costos_entregas',
                'campo_estatus' => 'created_at',
                'operador' => '>=',
                'valor_estatus' => '-7 days',
                'condiciones_extra' => null,
                'rol' => 'compras',
                'nav_href' => '/admin/costos/facturas',
                'filter_href' => '/admin/costos/facturas',
            ],
            [
                // Requisiciones cotizadas con OC ya configurada que aún esperan la
                // verificación gerencial (control). Le avisa a compras cuántas hay.
                'nombre' => 'Requisiciones con OC pendientes de verificación gerencial',
                'tabla' => 'costos_requisiciones',
                'campo_estatus' => 'estatus',
                'operador' => '=',
                'valor_estatus' => 'cotizada',
                'condiciones_extra' => [
                    ['campo' => 'control_verificado', 'operador' => '=', 'valor' => false],
                    ['tipo' => 'existe', 'tabla' => 'costos_requisicion_ocs', 'fk' => 'requisicion_id'],
                ],
                'rol' => 'compras',
                'nav_href' => '/admin/costos/requisiciones',
                'filter_href' => '/admin/costos/requisiciones?estatus=cotizada',
            ],
        ];

        foreach ($configs as $config) {
            BadgeConfig::updateOrCreate(
                ['nombre' => $config['nombre']],
                $config,
            );
        }
    }
}
