<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vacía el movimiento del módulo de producción antes de reestructurarlo.
     *
     * El catálogo pasa de "un renglón por modelo con cantidad N" a la jerarquía
     * Obra → Catálogo → Marca → Pieza (QS), y el destajo pasa a pagarse por
     * (pieza, proceso). Los datos que había eran de prueba, así que se borran en
     * vez de convertirse: no hay forma de inventar el QS de una pieza que nunca
     * lo tuvo, y un catálogo a medio migrar dejaría el tope mintiendo.
     *
     * Se conserva a propósito la configuración que cuesta recapturar y que no
     * depende del catálogo: grupos de trabajo con sus empleados, ubicaciones,
     * categorías de pieza y de empleado, tipos de pago extra y la configuración
     * del módulo.
     */
    public function up(): void
    {
        $tablas = [
            'prod_liquidacion_empleados',
            'prod_liquidacion_detalle',
            'prod_liquidaciones',
            'prod_pagos_extra',
            'prod_asistencias',
            'prod_registros',
            'prod_destajos',
            'prod_grupo_precio_conceptos',
            'prod_grupos_precio',
            'conceptos',
            'prod_catalogos',
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($tablas as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::table($tabla)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Sin vuelta atrás: los datos borrados eran de prueba y no se respaldan.
     */
    public function down(): void {}
};
