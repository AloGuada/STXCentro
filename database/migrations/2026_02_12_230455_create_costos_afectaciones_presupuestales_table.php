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
        Schema::create('costos_afectaciones_presupuestales', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->date('fecha');
            $table->string('tipo_origen');
            $table->text('descripcion');
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->string('estatus')->default('borrador');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignUuid('creado_por')->constrained('usuarios');
            $table->foreignUuid('aprobado_por')->nullable()->constrained('usuarios');
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->string('pdf_formato_path')->nullable();
            $table->string('pdf_firmado_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_afectaciones_presupuestales');
    }
};
