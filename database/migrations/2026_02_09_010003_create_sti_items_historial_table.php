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
        Schema::create('sti_items_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('sti_items')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('sti_tecnicos')->nullOnDelete();
            $table->nullableMorphs('relacionable');
            $table->string('accion');
            $table->dateTime('fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_items_historial');
    }
};
