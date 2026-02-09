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
        Schema::create('sti_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
            $table->foreignId('item_id')->unique()->constrained('sti_items')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_grupos');
    }
};
