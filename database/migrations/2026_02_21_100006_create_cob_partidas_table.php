<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('tipo')->default('suministro');
            $table->boolean('es_adicional')->default(false);
            $table->string('descripcion');
            $table->decimal('monto', 15, 2)->default(0);
            $table->string('moneda', 3)->default('MXN');
            $table->boolean('es_subobra')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_partidas');
    }
};
