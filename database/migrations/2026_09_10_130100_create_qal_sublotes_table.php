<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una inspección de sublote: una entrega parcial del lote, revisada por
     * muestreo AQL, con el plan que regía al capturarla.
     *
     * Una reinspección no pisa a la original: es otra fila que apunta a la
     * primera del grupo (`sublote_origen_id`) con el número siguiente. Por eso
     * la original no se deja borrar mientras tenga reinspecciones: el grupo se
     * quedaría sin su primera inspección, que es la que mide el FPY.
     */
    public function up(): void
    {
        Schema::create('qal_sublotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('qal_lotes_accesorios')->restrictOnDelete();
            $table->foreignId('sublote_origen_id')->nullable()->constrained('qal_sublotes')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_inspeccion')->default(1);
            $table->unsignedInteger('unidades');
            $table->date('fecha');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->foreignId('inspector_id')->constrained('qal_inspectores')->restrictOnDelete();
            $table->string('linea', 10)->nullable();
            $table->string('modulo', 60)->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('qal_responsables')->nullOnDelete();
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->string('nivel', 3);
            $table->unsignedInteger('muestra');
            $table->unsignedInteger('aceptacion');
            $table->unsignedInteger('rechazo');
            $table->unsignedInteger('conformes')->default(0);
            $table->unsignedInteger('rechazadas')->default(0);
            // Nulo mientras la muestra no se completa: en curso no es aceptado.
            $table->string('veredicto', 10)->nullable();
            $table->string('disposicion', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('capturado_en');
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['lote_id', 'fecha']);
            $table->index('sublote_origen_id');
            $table->index(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_sublotes');
    }
};
