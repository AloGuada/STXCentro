<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_grupos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion')->comment('Nombre del grupo de trabajadores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_grupos');
    }
};
