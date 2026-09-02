<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La entrega de material que se queda en el mismo domicilio, folio `SAL`.
     *
     * «Vale» es el impreso, no el documento: en la interfaz esto se llama
     * Salida, y *vale* queda sólo para el PDF que firma quien se lleva el
     * material. Mientras la salida era el único papel ambos nombres eran lo
     * mismo; con el pedido adentro, seguir diciendo «vale» hace que la gente
     * pida un vale cuando lo que debe levantar es un pedido.
     *
     * `pedido_id` es nullable: la salida directa se conserva para lo urgente.
     * El amarre fino va renglón a renglón en el detalle, porque un pedido puede
     * pedir el mismo artículo en dos renglones distintos.
     *
     * No afecta presupuesto: el gasto se reconoció en la compra, y Almacén sólo
     * controla artículos.
     */
    public function up(): void
    {
        Schema::create('alm_salidas', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->index();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('alm_pedidos')->nullOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('obra_destino_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->foreignId('grupo_trabajo_id')->nullable()->constrained('prod_grupos_trabajo')->nullOnDelete();
            $table->foreignUuid('solicitante_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignUuid('entregado_por')->constrained('usuarios');
            // Quien firma el vale. Va como texto porque muchas veces es una
            // cuadrilla o alguien que no tiene usuario en el sistema.
            $table->string('recibe_nombre');
            $table->date('fecha');
            $table->string('motivo')->nullable();
            $table->text('observaciones')->nullable();
            // Cancelación suave, como `costos_entregas`: el documento vive y
            // deja de contar. Su reverso queda en el kardex.
            $table->timestamp('cancelada_at')->nullable();
            $table->foreignUuid('cancelada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->index(['almacen_id', 'fecha']);
            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_salidas');
    }
};
