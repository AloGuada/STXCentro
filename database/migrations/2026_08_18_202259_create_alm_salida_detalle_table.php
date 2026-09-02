<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un renglón entregado.
     *
     * `pedido_detalle_id` amarra renglón con renglón y no sólo documento con
     * documento: con el amarre en la cabecera habría que casar por
     * `producto_id`, y eso falla en cuanto un pedido pide el mismo artículo en
     * dos renglones —con dos observaciones distintas, que es justo por lo que
     * los separaron.
     *
     * `costo_unitario` se sella al salir: es el costo promedio con el que se
     * descargó, y guardarlo evita tener que reconstruirlo desde el kardex para
     * imprimir el vale de hace un año.
     */
    public function up(): void
    {
        Schema::create('alm_salida_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salida_id')->constrained('alm_salidas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->nullable()->constrained('alm_pedido_detalle')->nullOnDelete();
            $table->decimal('cantidad', 16, 4);
            $table->decimal('costo_unitario', 16, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('producto_id');
            $table->index('pedido_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_salida_detalle');
    }
};
