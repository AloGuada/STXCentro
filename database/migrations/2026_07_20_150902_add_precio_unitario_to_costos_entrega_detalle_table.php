<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precio unitario recibido: el almacén puede capturarlo al recepcionar para
     * igualarlo a la factura (ej. acero, cuyo precio cambia entre cotización y
     * entrega). Nulo = se recibió al precio de la OC. La partida de la OC conserva
     * su precio original intacto, de modo que quedan ambos: contratado y recibido.
     */
    public function up(): void
    {
        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->decimal('precio_unitario', 14, 2)->nullable()->after('cantidad_recibida');
        });
    }

    public function down(): void
    {
        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->dropColumn('precio_unitario');
        });
    }
};
