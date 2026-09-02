<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El ajuste: el único documento que cambia la existencia sin que haya
     * entrado ni salido material.
     *
     * Existe porque los documentos son inmutables. Sin él, un error de captura
     * sólo se corrige metiendo el movimiento contrario, y el kardex queda con
     * dos renglones que no explican nada. Aquí queda con folio, motivo y quién
     * lo autorizó.
     *
     * También es la puerta de la carga inicial y el cierre de un conteo cíclico:
     * el conteo no toca el saldo por su cuenta, genera uno de éstos.
     */
    public function up(): void
    {
        Schema::create('alm_ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->index();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->string('motivo', 20);
            $table->foreignUuid('autorizado_por')->constrained('usuarios');
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['almacen_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_ajustes');
    }
};
