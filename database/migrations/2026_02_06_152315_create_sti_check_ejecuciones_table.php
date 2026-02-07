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
        Schema::create('sti_check_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mantenimiento_id')->constrained('sti_mantenimientos')->cascadeOnDelete();
            $table->foreignId('check_id')->constrained('sti_checks')->cascadeOnDelete();
            $table->boolean('resultado')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('tecnico_id')->nullable()->constrained('sti_tecnicos')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_check_ejecuciones');
    }
};
