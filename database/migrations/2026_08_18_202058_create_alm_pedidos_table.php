<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que un área le pide a un almacén, folio `PED`.
     *
     * Se llama pedido y no requisición para no chocar con la requisición de
     * compra de Costos, que le pide material a un proveedor. Éste le pide a un
     * almacén lo que ya está en existencia.
     *
     * `departamento_id` va siempre —siempre hay un área responsable— y `obra_id`
     * es nullable: `null` es consumo interno de planta. Fabricación, pintura y
     * mantenimiento también piden material y no cuelgan de ninguna obra.
     *
     * De ahí sale cómo se surte: **con obra** hay que llevarlo a otro domicilio,
     * así que lo surte una transferencia y la obra confirma; **sin obra** el
     * material se queda en el mismo sitio y sale con una salida.
     */
    public function up(): void
    {
        Schema::create('alm_pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->index();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->foreignUuid('solicitante_id')->constrained('usuarios');
            // Sólo en los internos de planta: a nombre de quién se entrega. El
            // área levanta el pedido y no siempre sabe de antemano quién va a
            // pasar por el material.
            $table->string('recibe_nombre')->nullable();
            $table->foreignId('grupo_trabajo_id')->nullable()->constrained('prod_grupos_trabajo')->nullOnDelete();
            $table->date('fecha');
            $table->date('fecha_requerida');
            $table->string('motivo')->nullable();
            $table->string('estatus', 30)->default('aprobado');
            $table->foreignUuid('aprobado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Los dos filtros de la pantalla que más se consulta: qué le falta
            // surtir a este almacén, y qué pidió esta obra.
            $table->index(['almacen_id', 'estatus']);
            $table->index(['obra_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_pedidos');
    }
};
