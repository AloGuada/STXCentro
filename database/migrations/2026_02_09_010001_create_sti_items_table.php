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
        Schema::create('sti_items', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->foreignId('tipo_id')->constrained('sti_items_tipos')->cascadeOnDelete();
            $table->decimal('costo', 10, 2)->default(0);
            $table->string('no_serie')->nullable();
            $table->string('estado')->default('disponible');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_items');
    }
};
