<?php

use App\Enums\Alm\ClasificacionAbc;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que le faltaba al catálogo compartido para que Almacén lo pueda
     * administrar como suyo.
     *
     * - `codigo_barras` es lo que se escanea. Nace igual al código y se puede
     *   sobrescribir con el del fabricante cuando la caja ya trae uno impreso,
     *   así que no es único: dos artículos distintos jamás deberían compartirlo,
     *   pero forzarlo por índice rompería el alta de quien copia mal una caja.
     * - `idsteelex` es cómo se llama el artículo en el sistema anterior. Texto
     *   libre, no se valida ni se cruza: sólo sirve para conciliar mientras los
     *   dos convivan.
     * - `area_id` clasifica a qué parte de la operación pertenece. No es dónde
     *   está guardado —eso es `alm_ubicaciones`—: el área viaja con el artículo.
     * - `clasificacion_abc` decide cada cuánto lo alcanza el inventario cíclico.
     *   Nace en C porque contar seguido lo barato cuesta más de lo que vale.
     */
    public function up(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->string('codigo_barras')->nullable()->after('codigo');
            $table->string('idsteelex', 150)->nullable()->after('descripcion');
            $table->foreignId('area_id')->nullable()->after('idsteelex')
                ->constrained('alm_areas')->nullOnDelete();
            $table->string('clasificacion_abc', 1)
                ->default(ClasificacionAbc::C->value)
                ->after('stock_minimo');

            $table->index('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->dropIndex(['codigo_barras']);
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['codigo_barras', 'idsteelex', 'clasificacion_abc']);
        });
    }
};
