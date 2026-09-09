<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con quién se fundió un renglón que ya no cuenta.
     *
     * La carga inicial dio de alta el mismo insumo una vez por almacén, y
     * juntarlos no puede ser borrar: las etiquetas impresas, los vales y los
     * asientos viejos siguen diciendo el código del sobrante. `fusionado_en_id`
     * es la flecha que lleva de ese código al que sobrevivió, para que quien
     * escanee ART-01428 encuentre ART-00418 en vez de un renglón desactivado
     * sin explicación.
     *
     * Va en las dos tablas porque la fusión es de identidades y cada lado tiene
     * la suya: el artículo de Almacén y el producto de Compras.
     *
     * `nullOnDelete` y no restrict: si el sobreviviente algún día se borrara,
     * la flecha se queda sin destino, pero eso no debe impedir la baja.
     */
    public function up(): void
    {
        Schema::table('alm_articulos', function (Blueprint $table) {
            $table->foreignId('fusionado_en_id')->nullable()->after('activo')
                ->constrained('alm_articulos')->nullOnDelete();
        });

        Schema::table('costos_productos', function (Blueprint $table) {
            $table->foreignId('fusionado_en_id')->nullable()->after('activo')
                ->constrained('costos_productos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alm_articulos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fusionado_en_id');
        });

        Schema::table('costos_productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fusionado_en_id');
        });
    }
};
