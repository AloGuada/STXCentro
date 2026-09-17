<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La cabecera de una inspección, común a las tres transformaciones.
     *
     * Sustituye a la tabla `registros` de la aplicación anterior, que traía unas
     * 140 columnas: lo que era de cada fase se fue a sus propias tablas (puntos,
     * defectos, muestreo, pintura, juntas) y aquí queda sólo lo que todas
     * comparten.
     *
     * La pieza sale de Producción y se referencia distinto según la fase:
     *
     *  - En 1ª por la marca (`concepto_id`) y el consecutivo. Se sabe que no
     *    identifica la pieza física, pero en corte todavía no hay QR.
     *  - En 2ª y pintura por la pieza física (`prod_pieza_id`, el QR), y la
     *    marca se deduce de ella.
     *
     * Marca, lote y QR se copian como texto: el catálogo de Producción se
     * versiona y se vacía, y una inspección tiene que seguir diciendo de qué
     * pieza habla aunque la marca ya no exista. Por eso las tres llaves a
     * Producción se anulan en vez de impedir el borrado.
     *
     * `numero_inspeccion` lo calcula el servidor: la misma pieza se
     * reinspecciona tras repararse, y con él se mide el FPY.
     */
    public function up(): void
    {
        Schema::create('qal_inspecciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('catalogo_id')->nullable()->constrained('prod_catalogos')->nullOnDelete();
            $table->foreignId('concepto_id')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->foreignId('prod_pieza_id')->nullable()->constrained('prod_piezas')->nullOnDelete();
            $table->string('marca', 80);
            $table->string('lote', 60)->nullable();
            $table->string('qr', 80)->nullable();
            $table->foreignId('tipo_pieza_id')->nullable()->constrained('qal_tipos_pieza')->nullOnDelete();
            $table->string('fase', 4);
            $table->string('subetapa', 20)->nullable();
            $table->string('subtipo', 10)->nullable();
            $table->date('fecha');
            // La semana va con su año: sola es ambigua entre ejercicios.
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->foreignId('inspector_id')->constrained('qal_inspectores')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_inspeccion')->default(1);
            $table->unsignedInteger('consecutivo')->nullable();
            $table->unsignedInteger('cantidad_lote')->nullable();
            $table->decimal('kg', 12, 3);
            $table->string('folio_strumis', 40)->nullable();
            $table->string('linea', 10)->nullable();
            $table->string('modulo', 60)->nullable();
            $table->foreignId('equipo_id')->nullable()->constrained('qal_equipos')->nullOnDelete();
            $table->foreignId('operador_id')->nullable()->constrained('qal_operadores')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('qal_responsables')->nullOnDelete();
            $table->foreignId('supervisor_pintura_id')->nullable()->constrained('qal_supervisores_pintura')->nullOnDelete();
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->string('estatus', 12)->default('pendiente');
            // IV-50 y IS-60: el avance de vestido y soldadura que imprime el formato.
            $table->string('avance_iv', 3)->nullable();
            $table->string('avance_is', 3)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('capturado_en');
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['obra_id', 'fase', 'fecha']);
            $table->index(['obra_id', 'qr', 'fase']);
            $table->index(['obra_id', 'marca', 'fase']);
            $table->index(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_inspecciones');
    }
};
