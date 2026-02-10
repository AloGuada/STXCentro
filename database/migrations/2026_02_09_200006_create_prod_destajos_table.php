<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_destajos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('prod_grupos')->cascadeOnDelete();
            $table->date('semana')->comment('Fecha inicio de la semana del destajo');
            $table->boolean('cerrado')->default(false)->comment('Si esta cerrado ya no se puede editar');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_destajos');
    }
};
