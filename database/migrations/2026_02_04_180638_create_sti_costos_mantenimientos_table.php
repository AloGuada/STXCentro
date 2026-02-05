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
        Schema::create('sti_costos_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 2);
            $table->morphs('costeable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_costos_mantenimientos');
    }
};
