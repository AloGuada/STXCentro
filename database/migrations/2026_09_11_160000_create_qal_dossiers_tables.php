<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El dosier de una obra: un repositorio de PDF por sección.
     *
     * Nace copiando el árbol de una plantilla —cambiar la plantilla después no
     * lo toca— y guarda el nombre de la plantilla por si ésta se renombra o se
     * desactiva. Una obra tiene un solo dosier.
     *
     * Los PDF van al disco privado: son documentos del cliente y se sirven con
     * permiso. `compatible` dice si el motor de unión actual los puede abrir;
     * los que no, quedan fuera de la descarga y la pantalla lo avisa.
     */
    public function up(): void
    {
        Schema::create('qal_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->unique()->constrained('obras')->restrictOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('qal_dossier_plantillas')->nullOnDelete();
            $table->string('plantilla_nombre', 120);
            $table->string('estatus', 12)->default('borrador');
            $table->timestamp('entregado_at')->nullable();
            $table->text('notas')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('qal_dossier_secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('qal_dossiers')->cascadeOnDelete();
            $table->foreignId('padre_id')->nullable()->constrained('qal_dossier_secciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('titulo', 160);
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->index(['dossier_id', 'padre_id', 'orden']);
        });

        Schema::create('qal_dossier_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('qal_dossier_secciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('nombre_original');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('paginas')->nullable();
            $table->boolean('compatible')->default(true);
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['seccion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_dossier_archivos');
        Schema::dropIfExists('qal_dossier_secciones');
        Schema::dropIfExists('qal_dossiers');
    }
};
