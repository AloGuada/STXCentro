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
        Schema::create('cal_flechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('cal_reportes')->cascadeOnDelete();
            $table->decimal('inicio_x', 10, 4);
            $table->decimal('inicio_y', 10, 4);
            $table->decimal('fin_x', 10, 4);
            $table->decimal('fin_y', 10, 4);
            $table->boolean('esdoble')->default(false);
            $table->string('tipo')->nullable();
            $table->foreignId('soldador_id')->nullable()->constrained('cal_soldadores');
            $table->boolean('show_number')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cal_flechas');
    }
};
