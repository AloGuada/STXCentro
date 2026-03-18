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
        Schema::create('cal_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('cal_piezas_planos')->cascadeOnDelete();
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
            $table->string('folio')->nullable();
            $table->foreignId('soldador_id')->nullable()->constrained('cal_soldadores');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cal_reportes');
    }
};
