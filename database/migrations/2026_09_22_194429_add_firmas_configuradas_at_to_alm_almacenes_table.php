<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuándo alguien dejó configuradas las firmas del almacén.
     *
     * Sin esto, «no hay renglones» y «nadie lo ha configurado» eran lo mismo:
     * borrar todas las firmas de un documento y guardar lo regresaba a la
     * plantilla. Con la marca, el almacén sin configurar imprime la plantilla y
     * el configurado imprime lo suyo, aunque lo suyo sea ninguna raya.
     */
    public function up(): void
    {
        Schema::table('alm_almacenes', function (Blueprint $table) {
            $table->timestamp('firmas_configuradas_at')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('alm_almacenes', function (Blueprint $table) {
            $table->dropColumn('firmas_configuradas_at');
        });
    }
};
