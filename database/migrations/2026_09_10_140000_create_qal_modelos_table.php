<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El modelo 3D de una obra: el IFC de Tekla convertido en una marca por
     * archivo, con los cordones de soldadura que se detectaron por geometría.
     *
     * Cuelga de la obra y no del catálogo de Producción: la geometría no cambia
     * porque se versione el catálogo, y la marca se vuelve a amarrar al vigente
     * cuando hace falta. Un IFC nuevo es una versión nueva; cada versión
     * conserva sus cordones, porque sobre ellos se capturaron juntas.
     *
     * La conversión corre en el servicio aparte `ifc-service`; aquí se guarda
     * el trabajo que la está haciendo y cómo terminó.
     */
    public function up(): void
    {
        Schema::create('qal_modelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            // La versión del catálogo vigente al subirlo, sólo como referencia.
            $table->foreignId('catalogo_id')->nullable()->constrained('prod_catalogos')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('archivo_ifc')->nullable();
            $table->string('nombre_original');
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->string('estatus', 12)->default('pendiente');
            $table->string('trabajo_externo_id', 64)->nullable();
            // Con qué versión del algoritmo se calcularon los cordones.
            $table->string('welds_version', 40)->nullable();
            $table->json('resumen')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['obra_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_modelos');
    }
};
