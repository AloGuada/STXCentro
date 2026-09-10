<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un renglón de lo pedido.
     *
     * `cantidad_surtida` es columna cacheada, no accessor: la pantalla de
     * «pedidos surtibles» filtra por renglones que aún deben algo, y derivarla
     * obligaría a traer todos los pedidos con todas sus salidas y transferencias
     * a PHP para poder listar cuáles siguen abiertos.
     *
     * La recalcula `SurtidoPedido` con un `SUM` sobre los documentos vivos —
     * nunca se incrementa. Así, cancelar una salida la deja correcta sin
     * escribir lógica de reverso.
     */
    public function up(): void
    {
        Schema::create('alm_pedido_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('alm_pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 16, 4);
            $table->decimal('cantidad_surtida', 16, 4)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_pedido_detalle');
    }
};
