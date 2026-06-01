<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->foreignId('uso_cfdi_id')->nullable()->after('obra_rubro_id')
                ->constrained('costos_usos_cfdi')->nullOnDelete();
            $table->string('codigo_producto')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uso_cfdi_id');
            $table->dropColumn('codigo_producto');
        });
    }
};
