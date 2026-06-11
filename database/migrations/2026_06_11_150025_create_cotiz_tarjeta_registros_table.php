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
        Schema::create('cotiz_tarjeta_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            // De generadora: referencia al registro origen (NULL si es manual).
            $table->foreignId('generadora_registro_id')->nullable()->constrained('cotiz_generadora_registros')->cascadeOnDelete();
            // Manual: insumo + cantidad capturados a mano (usados cuando generadora_registro_id es NULL).
            $table->foreignId('insumo_id')->nullable()->constrained('cotiz_insumos')->nullOnDelete();
            $table->decimal('cantidad', 16, 6)->nullable();
            $table->decimal('importe', 16, 4)->nullable();
            $table->boolean('validado')->default(false);
            // M030: selector por-registro de fórmula de área de pintura.
            $table->string('tipo_pintura')->default('auto');
            $table->timestamps();

            $table->index('tarjeta_id');
            // Un generadora_registro no puede importarse en dos tarjetas (NULL múltiples permitidos = manuales).
            $table->unique('generadora_registro_id');
            // Invariante (de generadora | manual) se valida en el controlador/Form Request para
            // no introducir un CHECK que diverja entre SQLite (dev) y PostgreSQL (prod).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjeta_registros');
    }
};
