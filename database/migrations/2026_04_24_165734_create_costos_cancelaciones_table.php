<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_cancelaciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('cancelable');
            $table->string('motivo', 500);
            $table->foreignUuid('usuario_id')->constrained('usuarios');
            $table->timestamp('cancelado_at')->useCurrent();
            $table->timestamps();

            $table->unique(['cancelable_type', 'cancelable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_cancelaciones');
    }
};
