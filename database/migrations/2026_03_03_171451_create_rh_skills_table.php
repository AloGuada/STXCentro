<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_skills', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['hard', 'soft']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_skills');
    }
};
