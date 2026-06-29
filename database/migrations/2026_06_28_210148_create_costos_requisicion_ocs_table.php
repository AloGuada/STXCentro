<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatos de cada OC planeada de una requisición — definidos en el tab "OC"
 * antes de aprobar, para que el aprobador valide modo de pago, fecha de entrega
 * y notas. Al liberar, OrdenCompraGenerator lee de aquí (ya no del payload del
 * cliente). Clave por (requisicion, proveedor, numero_oc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisicion_ocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('costos_requisiciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->unsignedInteger('numero_oc');
            $table->string('modo_pago')->default('contado');
            // Instrumento de pago para la(s) solicitud(es) de contado generadas
            // al liberar (espeja SolicitudPago.tipo_pago): transferencia|cheque|efectivo.
            $table->string('metodo_pago')->default('transferencia');
            $table->date('fecha_entrega')->nullable();
            $table->text('notas')->nullable();
            // Parcialidades de pago por % (solo contado): [{porcentaje, concepto}].
            // Vacío/null = pago único. Se generan N solicitudes al liberar.
            $table->json('pagos')->nullable();
            $table->timestamps();

            $table->unique(['requisicion_id', 'proveedor_id', 'numero_oc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisicion_ocs');
    }
};
