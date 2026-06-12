<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_versiones', function (Blueprint $table) {
            // Snapshot nombrado del árbol de INPUTS de una obra (etapa B1). Modelo LINEAL:
            // historial cronológico append-only, sin parentesco/ramas. Restaurar sobrescribe
            // los inputs actuales (tras auto-guardar un respaldo).
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('nota')->nullable();
            $table->boolean('auto')->default(false); // true = respaldo automático antes de restaurar
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->json('snapshot'); // árbol de inputs serializado (el cálculo es derivado)
            $table->timestamps();

            $table->index(['obra_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_versiones');
    }
};
