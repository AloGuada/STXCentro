<?php

namespace App\Console\Commands\Costos;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borra TODO el flujo transaccional de Costos (requisiciones, solicitudes de
 * pago con sus archivos, OCs, entregas, facturas, notas de crédito, pagos,
 * anticipos, afectaciones, aprobaciones, cancelaciones e histórico de precios)
 * y resetea el acumulado de los centros de costos. NO toca catálogos ni config
 * (rubros, productos, proveedores, usos CFDI, tipos y sus documentos
 * configurados, permisos, configuración).
 */
class LimpiarFlujoCommand extends Command
{
    protected $signature = 'costos:limpiar-flujo {--force : Omitir confirmación}';

    protected $description = 'Borra todo el flujo transaccional de costos y resetea el acumulado de los centros de costos (no toca catálogos).';

    /**
     * Tablas transaccionales a vaciar. Se omiten las que no existan.
     *
     * @var list<string>
     */
    private const TABLAS = [
        // Requisición y su cotización/selección/OC-meta
        'costos_requisicion_seleccion',
        'costos_requisicion_cotizacion_precio',
        'costos_requisicion_ocs',
        'costos_requisicion_detalle',
        'costos_requisiciones',
        // Solicitudes de pago
        'costos_solicitud_archivos',
        'costos_solicitudes_pago_detalle',
        'costos_solicitudes_pago',
        // Órdenes de compra y cadena de recepción/facturación/pago
        'costos_entrega_detalle',
        'costos_entregas',
        'costos_devoluciones',
        'costos_factura_detalle',
        'costos_notas_credito',
        'costos_facturas',
        'costos_complementos_pago',
        'costos_anticipo_aplicaciones',
        'costos_anticipos',
        'costos_pagos',
        'costos_ordenes_compra_detalle',
        'costos_ordenes_compra',
        // Aprobaciones / cancelaciones (polimórficas)
        'costos_aprobaciones',
        'costos_aprobaciones_solicitud',
        'costos_cancelaciones',
        // Presupuesto: afectaciones e histórico de precios
        'costos_afectaciones_detalle',
        'costos_afectaciones_historial',
        'costos_afectaciones_presupuestales',
        'costos_rubros_afectados',
        'costos_producto_precios',
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Bloqueado en producción. Ejecuta con --force solo si estás seguro.');

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm('Esto BORRA todo el flujo transaccional de costos y resetea el acumulado de los centros de costos. ¿Continuar?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $total = 0;

        Schema::withoutForeignKeyConstraints(function () use (&$total) {
            foreach (self::TABLAS as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }

                $borrados = DB::table($tabla)->delete();
                $total += $borrados;

                if ($borrados > 0) {
                    $this->line("  {$tabla}: {$borrados}");
                }
            }

            $rubros = DB::table('costos_obra_rubros')->update(['acumulado' => 0]);
            $this->line("  costos_obra_rubros: acumulado reseteado en {$rubros}");
        });

        $this->info("Flujo de costos limpiado ({$total} registros borrados). Catálogos y configuración intactos.");

        return self::SUCCESS;
    }
}
