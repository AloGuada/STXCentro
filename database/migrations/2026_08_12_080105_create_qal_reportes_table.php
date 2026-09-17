<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El reporte de inspección sobre un plano.
     *
     * El `folio` nace único, que en `cal_reportes` costó una migración aparte y
     * un comando para deduplicar lo que ya se había capturado. Los reportes
     * `es_plantilla` llevan folio NULL por diseño, y NULL no cuenta como
     * duplicado en SQLite, PostgreSQL ni MySQL: el índice admite N nulos.
     */
    public function up(): void
    {
        Schema::create('qal_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('qal_piezas_planos')->cascadeOnDelete();
            $table->string('strumis_id')->nullable();
            $table->string('consecutivo')->nullable();
            $table->foreignUuid('inspector_id')->nullable()->constrained('usuarios');
            $table->string('plantilla')->nullable();
            $table->timestamp('aprobado')->nullable();
            $table->timestamp('rechazado')->nullable();
            $table->boolean('es_plantilla')->default(false);
            $table->integer('linea')->nullable();
            $table->integer('modulo')->nullable();
            $table->text('comentario')->nullable();
            $table->string('folio')->nullable()->unique('qal_reportes_folio_unique');
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_reportes');
    }
};
