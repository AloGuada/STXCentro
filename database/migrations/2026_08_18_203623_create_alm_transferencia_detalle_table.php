<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que salió y lo que llegó de cada artículo.
     *
     * `cantidad_recibida` es nullable a propósito: `null` es «todavía no se
     * confirma», que es distinto de «llegaron cero». Sin esa distinción no hay
     * dónde guardar el faltante, y el segundo tiempo del documento no
     * significaría nada.
     *
     * `costo_unitario` se sella al enviar, con el promedio del origen, y el
     * destino carga con ese mismo número. Si no, mover material entre bodegas
     * inventaría o destruiría valor.
     */
    public function up(): void
    {
        Schema::create('alm_transferencia_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transferencia_id')->constrained('alm_transferencias')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->nullable()->constrained('alm_pedido_detalle')->nullOnDelete();
            $table->decimal('cantidad_enviada', 16, 4);
            $table->decimal('cantidad_recibida', 16, 4)->nullable();
            $table->decimal('costo_unitario', 16, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('producto_id');
            $table->index('pedido_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_transferencia_detalle');
    }
};
