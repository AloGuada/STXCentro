<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('op')->nullable();
            $table->decimal('factor_contratista', 8, 4)->default(1.15);
            $table->unsignedInteger('num_grupos')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obras');
    }
};
