<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset del módulo de costos: vacía datos transaccionales + presupuestos
 * (obra_rubros) + proveedores. CONSERVA los catálogos base y la configuración
 * de aprobación:
 *   - costos_tipo_rubros, costos_rubros
 *   - costos_tipo_solicitud, costos_documentos
 *   - costos_usos_cfdi
 *   - costos_permisos, costos_aprobacion_departamento (niveles de aprobación)
 *
 * DESTRUCTIVO. Ejecutar manualmente:
 *   php artisan db:seed --class=CostosResetSeeder
 *
 * No borra archivos físicos en storage (solo los registros en la tabla media
 * de las entidades de costos/proveedores).
 */
class CostosResetSeeder extends Seeder
{
    /**
     * Tablas que se vacían, en orden hijo → padre (igual da con las FK
     * deshabilitadas, pero el orden documenta las dependencias).
     *
     * @var list<string>
     */
    private array $tablas = [
        // Requisiciones
        'costos_requisicion_seleccion',
        'costos_requisicion_cotizacion_precio',
        'costos_requisicion_detalle',
        'costos_requisiciones',
        // Órdenes de compra
        'costos_ordenes_compra_detalle',
        'costos_ordenes_compra',
        // Facturas
        'costos_factura_detalle',
        'costos_facturas',
        // Notas de crédito y complementos de pago
        'costos_notas_credito',
        'costos_complementos_pago',
        // Pagos
        'costos_pagos',
        // Solicitudes de pago
        'costos_solicitud_archivos',
        'costos_solicitudes_pago_detalle',
        'costos_solicitudes_pago',
        // Aprobaciones (polimórficas) + tabla legada
        'costos_aprobaciones',
        'costos_aprobaciones_solicitud',
        // Afectaciones presupuestales
        'costos_afectaciones_detalle',
        'costos_afectaciones_historial',
        'costos_afectaciones_presupuestales',
        // Entregas y devoluciones
        'costos_entrega_detalle',
        'costos_entregas',
        'costos_devoluciones',
        // Anticipos
        'costos_anticipo_aplicaciones',
        'costos_anticipos',
        // Presupuestos
        'costos_rubros_afectados',
        'costos_obra_rubros',
        // Cancelaciones (polimórficas)
        'costos_cancelaciones',
        // Proveedores
        'proveedor_password_reset_tokens',
        'proveedores',
    ];

    public function run(): void
    {
        if (app()->environment('production') && ! env('RESET_COSTOS_FORCE')) {
            throw new \RuntimeException(
                'CostosResetSeeder está bloqueado en producción. Define RESET_COSTOS_FORCE=true para permitirlo.'
            );
        }

        Schema::disableForeignKeyConstraints();

        try {
            // Limpia los registros de media de entidades de costos y proveedores
            // (los archivos físicos en storage no se tocan). Va dentro del bloque
            // con FKs deshabilitadas porque tablas como costos_solicitud_archivos
            // y costos_requisicion_cotizacion_precio referencian media.
            DB::table('media')
                ->where('mediable_type', 'like', 'App\\Models\\Costos\\%')
                ->orWhere('mediable_type', \App\Models\Proveedor::class)
                ->delete();

            foreach ($this->tablas as $tabla) {
                DB::table($tabla)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command->info('Costos reseteado: '.count($this->tablas).' tablas vaciadas. Catálogos y niveles de aprobación conservados.');
    }
}
