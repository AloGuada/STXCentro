<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué rayas de firma lleva el formato impreso de cada documento.
     *
     * Van por almacén porque quien firma en el general no es quien firma en
     * obra. El almacén que nunca se configuró no tiene renglones aquí y cae en
     * la plantilla del enum: la tabla guarda sólo lo que alguien cambió.
     */
    public function up(): void
    {
        Schema::create('alm_firmas_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->cascadeOnDelete();
            $table->string('documento', 20);
            $table->unsignedTinyInteger('orden');
            $table->string('rotulo', 60);
            // Atributo o relación del documento de donde sale el nombre que se
            // imprime arriba de la raya. Nulo = la raya va siempre en blanco.
            $table->string('fuente', 30)->nullable();
            $table->timestamps();

            $table->unique(['almacen_id', 'documento', 'orden']);
            $table->index(['almacen_id', 'documento']);
        });

        // Los usuarios fijos de un renglón. Con varios se imprimen separados
        // por «/»: basta con que firme cualquiera de ellos.
        Schema::create('alm_firma_documento_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_documento_id')->constrained('alm_firmas_documento')->cascadeOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();

            $table->unique(['firma_documento_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_firma_documento_usuarios');
        Schema::dropIfExists('alm_firmas_documento');
    }
};
