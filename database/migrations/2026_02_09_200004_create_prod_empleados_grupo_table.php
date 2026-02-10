<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_empleados_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('prod_grupos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('no_empleado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_empleados_grupo');
    }
};
