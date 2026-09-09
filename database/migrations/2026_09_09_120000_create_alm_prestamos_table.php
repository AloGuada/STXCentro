<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El resguardo: quién se llevó qué activos, a dónde y hasta cuándo.
     *
     * No mueve saldo. Lo prestado sigue siendo del almacén y sigue pesando en
     * su existencia; lo que cambia es la custodia, y por eso este documento
     * vive aparte del kardex. Cierra solo cuando todos sus renglones volvieron.
     *
     * El destino es obra **o** grupo de trabajo, o ninguno (se queda en
     * planta sin cuadrilla). No se obliga: lo que responde por el material es
     * la persona, y eso sí es obligatorio.
     */
    public function up(): void
    {
        Schema::create('alm_prestamos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->index();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignUuid('responsable_id')->constrained('usuarios');
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->foreignId('grupo_trabajo_id')->nullable()->constrained('prod_grupos_trabajo')->nullOnDelete();
            $table->date('fecha_salida');
            $table->date('fecha_retorno_esperada')->nullable();
            $table->string('estatus', 20);
            $table->foreignUuid('autorizado_por')->nullable()->constrained('usuarios');
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios');
            $table->timestamp('cerrado_en')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['almacen_id', 'estatus']);
            $table->index(['responsable_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_prestamos');
    }
};
