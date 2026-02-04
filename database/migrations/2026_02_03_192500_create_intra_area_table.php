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
        Schema::create('intra_area', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->foreignId('parent_id')->nullable()->constrained('intra_area')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intra_area');
    }
};
