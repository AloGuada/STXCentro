<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El informe que entrega el laboratorio de pruebas no destructivas.
     *
     * Es sólo el encabezado. En la aplicación anterior estos catorce datos se
     * repetían dentro de **cada junta** del informe, así que corregir el nombre
     * del laboratorio obligaba a tocar las cincuenta filas —y en la práctica lo
     * que hacía era borrar el informe entero y volver a insertarlo—. Aquí se
     * guarda una sola vez y las juntas cuelgan de él.
     *
     * `reporte_no` es el folio del laboratorio, no uno nuestro: por eso es único
     * y por eso no se genera, se teclea.
     *
     * La semana va con su año (`anio` + `semana`, ISO). La semana sola es
     * ambigua entre ejercicios y el tablero suma por semana.
     */
    public function up(): void
    {
        Schema::create('qal_pnd_reportes', function (Blueprint $table) {
            $table->id();
            $table->string('reporte_no')->unique('qal_pnd_reportes_reporte_no_unique');
            $table->string('metodo', 2);
            $table->foreignId('laboratorio_id')->constrained('qal_laboratorios');
            $table->foreignId('qal_obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->string('lugar')->nullable();
            $table->date('fecha_prueba');
            $table->date('fecha_emision')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->decimal('porcentaje_inspeccion', 5, 2)->nullable();
            $table->string('tecnico')->nullable();
            $table->string('material')->nullable();
            $table->string('norma')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['qal_obra_id', 'metodo']);
            $table->index(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pnd_reportes');
    }
};
