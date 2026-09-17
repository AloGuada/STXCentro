<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El plan de una semana: lo que producción piensa fabricar (2ª) o pintar
     * (3ª) en una obra.
     *
     * Va uno por obra, semana y transformación, no uno por obra y semana como
     * pedía la especificación: 2ª y pintura no van al mismo ritmo, y una pieza
     * que se termina el viernes no da tiempo a pintarse esa semana. Es lo que ya
     * hacía la aplicación anterior.
     */
    public function up(): void
    {
        Schema::create('qal_programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('fase', 4);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->text('notas')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['obra_id', 'fase', 'anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_programaciones');
    }
};
