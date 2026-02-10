<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_grupo_precios', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->decimal('precio', 10, 2)->default(0)->comment('Precio por kg del grupo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_grupo_precios');
    }
};
