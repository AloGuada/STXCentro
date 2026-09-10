<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El préstamo también surte pedidos.
     *
     * Un pedido pide lo que hace falta sin distinguir si se gasta o si regresa;
     * es al surtirlo cuando cada renglón toma su documento: el insumo sale por
     * salida o transferencia, y la herramienta —el activo, con o sin serie— se
     * presta bajo resguardo. El amarre es el mismo que ya llevan la salida y la
     * transferencia: `pedido_detalle_id` en el renglón, y lo prestado cuenta
     * como surtido.
     */
    public function up(): void
    {
        Schema::table('alm_prestamos', function (Blueprint $table) {
            $table->foreignId('pedido_id')->nullable()->after('almacen_id')->constrained('alm_pedidos')->nullOnDelete();
        });

        Schema::table('alm_prestamo_detalle', function (Blueprint $table) {
            $table->foreignId('pedido_detalle_id')->nullable()->after('activo_id')->constrained('alm_pedido_detalle')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alm_prestamo_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_detalle_id');
        });

        Schema::table('alm_prestamos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_id');
        });
    }
};
