<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
 *
 * Borra con `delete()` y con las llaves foráneas VIVAS, por lo mismo que
 * `AlmResetSeeder` —ver su docblock—: en PostgreSQL `truncate()` se compila
 * como `TRUNCATE ... RESTART IDENTITY CASCADE`, y ese CASCADE vacía en cadena
 * toda tabla que referencie a la truncada, sin importar cómo se declaró su
 * llave. En SQLite se compila como `delete from`, así que en dev no se notaba.
 */
class CostosResetSeeder extends Seeder
{
    /**
     * Tablas que se vacían, en orden hijo → padre. El orden es obligatorio: el
     * borrado corre con las llaves foráneas vivas, así que una tabla fuera de
     * lugar hace que el `restrict` del hijo bloquee el borrado del padre.
     *
     * @var list<string>
     */
    private array $tablas = [
        // Requisiciones
        'costos_requisicion_seleccion',
        'costos_requisicion_cotizacion_precio',
        'costos_requisicion_cotizacion_opcion',
        'costos_requisicion_ocs',
        'costos_requisicion_detalle',
        'costos_requisiciones',
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
        // Órdenes de compra: van hasta acá porque facturas y entregas las
        // referencian sin `onDelete`, y con las llaves vivas eso bloquea.
        'costos_ordenes_compra_detalle',
        'costos_ordenes_compra',
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

        DB::transaction(function (): void {
            foreach ($this->tablas as $tabla) {
                DB::table($tabla)->delete();
            }

            // Los registros de media de entidades de costos y proveedores (los
            // archivos físicos en storage no se tocan). Van al final y no al
            // principio: `costos_solicitud_archivos.media_id` no declara
            // `onDelete`, así que con las llaves vivas borrar media primero
            // bloquearía hasta que su renglón ya no exista.
            DB::table('media')
                ->where('mediable_type', 'like', 'App\\Models\\Costos\\%')
                ->orWhere('mediable_type', \App\Models\Proveedor::class)
                ->delete();
        });

        $this->command->info('Costos reseteado: '.count($this->tablas).' tablas vaciadas. Catálogos y niveles de aprobación conservados.');
    }
}
