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
        Schema::create('rh_contactos_emergencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('telefono');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rh_contactos_emergencia');
    }
};
