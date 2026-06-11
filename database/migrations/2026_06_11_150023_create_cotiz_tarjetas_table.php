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
        Schema::create('cotiz_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('descripcion');
            $table->integer('orden')->default(0);
            // Cache del footer (M039): se reescribe al recalcular; lo lee el Resumen.
            // NULL = aún no calculado. importe_materiales = Σ(cantidad×P.U.) registros + factores.
            $table->decimal('importe_materiales', 16, 4)->nullable();
            $table->decimal('kilos_reales', 16, 4)->nullable();
            // Lock de edición estricto (App\Models\Concerns\HasEditLock).
            $table->foreignUuid('locked_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index('obra_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotiz_tarjetas');
    }
};
