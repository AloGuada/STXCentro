<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_centros_costos', function (Blueprint $table) {
            $table->id();
            $table->string('cod_coste')->unique();
            $table->string('concepto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_centros_costos');
    }
};
