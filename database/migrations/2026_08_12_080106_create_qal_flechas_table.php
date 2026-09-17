<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las flechas que el inspector dibuja sobre el plano del reporte.
     *
     * Las coordenadas van normalizadas (0..1) sobre la página, por eso caben en
     * decimales de cuatro posiciones: así el dibujo sobrevive a que el PDF se
     * reescale.
     *
     * `pagina` y `soldador_id` nacen aquí; en `cal_flechas` llegaron en tres
     * alteraciones sucesivas —una de ellas quitó `soldador_id` para volver a
     * ponerlo una semana después—.
     */
    public function up(): void
    {
        Schema::create('qal_flechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('qal_reportes')->cascadeOnDelete();
            $table->decimal('inicio_x', 10, 4);
            $table->decimal('inicio_y', 10, 4);
            $table->decimal('fin_x', 10, 4);
            $table->decimal('fin_y', 10, 4);
            $table->boolean('esdoble')->default(false);
            $table->string('tipo')->nullable();
            $table->boolean('show_number')->default(true);
            $table->integer('pagina')->default(1);
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_flechas');
    }
};
