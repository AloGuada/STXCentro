<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El kardex: un asiento por cada cambio de saldo, con lo que había antes y
     * lo que quedó después. Espeja `costos_rubro_movimientos`.
     *
     * Invariante: el `saldo_despues` del último movimiento de una existencia es,
     * por construcción, su `cantidad` vigente; y el `saldo_antes` de cada uno es
     * el `saldo_despues` del anterior. La pantalla del kardex **lee**
     * `saldo_despues` en vez de recalcular el corriente sumando en PHP — ése es
     * el punto entero del patrón.
     *
     * `referencia` guarda el folio del documento como texto además del morph:
     * el folio sobrevive aunque el documento se cancele, y el kardex de hace un
     * año no debería depender de que la tabla que lo originó siga existiendo.
     *
     * `activo_id` va sin llave foránea todavía porque `alm_activos` llega en su
     * propia rebanada; la restricción se agrega ahí.
     */
    public function up(): void
    {
        Schema::create('alm_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('existencia_id')->constrained('alm_existencias')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->decimal('cantidad', 16, 4);
            $table->decimal('saldo_antes', 16, 4);
            $table->decimal('saldo_despues', 16, 4);
            $table->decimal('costo_unitario', 16, 4)->nullable();
            $table->decimal('costo_promedio_despues', 16, 4)->nullable();
            $table->decimal('valor_despues', 18, 4)->nullable();
            $table->nullableMorphs('documento');
            $table->string('referencia', 40)->nullable();
            $table->foreignId('ubicacion_id')->nullable()->constrained('alm_ubicaciones')->nullOnDelete();
            $table->unsignedBigInteger('activo_id')->nullable();
            $table->boolean('es_reverso')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['existencia_id', 'id']);
            $table->index(['almacen_id', 'producto_id', 'id']);
            $table->index(['producto_id', 'created_at']);
            $table->index('activo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_movimientos');
    }
};
