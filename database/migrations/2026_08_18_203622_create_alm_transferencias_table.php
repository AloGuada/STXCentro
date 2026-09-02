<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El traslado de material entre almacenes, folio `TRA`.
     *
     * Un solo documento con **dos firmas**: el origen registra el envío y el
     * destino la recepción. Entre las dos, el material no es existencia de
     * nadie — va en el camión.
     *
     * Es el estándar de los ERP (SAP en dos pasos `641`/`101`, Odoo con
     * *transit location*, Dynamics con *Shipped* → *Received*), y el paso único
     * se reserva para el mismo sitio físico, que aquí no aplica: planta y obra
     * están a kilómetros.
     *
     * Las dos fechas y los dos usuarios son el punto. Sin `recibido_por` no hay
     * quién confirme lo que bajó del camión, y sin `faltante_responsable_id` la
     * diferencia no tiene dueño — que es justo lo que se pierde cuando el
     * documento se captura de un solo golpe.
     */
    public function up(): void
    {
        Schema::create('alm_transferencias', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->index();
            $table->foreignId('almacen_origen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('almacen_destino_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('alm_pedidos')->nullOnDelete();
            $table->string('estatus', 20)->default('en_transito');
            $table->foreignUuid('autorizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha_envio');
            $table->foreignUuid('enviado_por')->constrained('usuarios');
            $table->date('fecha_recepcion')->nullable();
            $table->foreignUuid('recibido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            // A quién se le carga lo que no llegó. Se pide al cerrar la
            // recepción, no antes: mientras va en el camión no se le debe a nadie.
            $table->foreignUuid('faltante_responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->foreignUuid('cancelada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamps();

            // Lo que va en el camino hacia acá: es la pregunta del almacén
            // destino, y la que alimenta el saldo en tránsito.
            $table->index(['almacen_destino_id', 'estatus']);
            $table->index(['almacen_origen_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_transferencias');
    }
};
