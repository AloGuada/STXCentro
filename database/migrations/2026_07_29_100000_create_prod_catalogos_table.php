<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_catalogos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->foreignId('catalogo_origen_id')->nullable()->constrained('prod_catalogos')->nullOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('vigente')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['obra_id', 'vigente']);
            $table->unique(['obra_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_catalogos');
    }
};
