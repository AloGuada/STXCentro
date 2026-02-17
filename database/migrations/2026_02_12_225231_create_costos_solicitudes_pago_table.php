<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('costos_solicitudes_pago', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignUuid('solicitante_id')->constrained('usuarios');
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->foreignId('tipo_solicitud_id')->constrained('costos_tipo_solicitud');
            $table->text('concepto');
            $table->text('justificacion')->nullable();
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->string('tipo_pago');
            $table->date('fecha_pago_solicitada')->nullable();
            $table->date('fecha_pago_realizada')->nullable();
            $table->string('referencia_pago')->nullable();
            $table->string('comprobante_aprobacion_presupuesto')->nullable();
            $table->string('estatus')->default('borrador');
            $table->unsignedBigInteger('afectacion_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_solicitudes_pago');
    }
};
