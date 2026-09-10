<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose mensual del seguimiento ICSOE. El unique (seguimiento, año, mes) es
 * lo que permite recalcular con upsert conservando los días cotizados que ya
 * capturó el usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_icsoe_meses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('cob_icsoe_seguimientos')->cascadeOnDelete();
            $table->smallInteger('anio');
            /** 1-12 (el bosquejo usaba meses 0-indexados de JavaScript). */
            $table->tinyInteger('mes');

            $table->unsignedSmallInteger('dias_proyecto')->default(0);
            /** SBC del catálogo al momento de calcular. */
            $table->decimal('sbc', 10, 2)->default(0);
            /** El realmente usado: puede ser un override manual del usuario. */
            $table->decimal('sbc_aplicado', 10, 2)->default(0);
            $table->decimal('mo_estimada', 15, 2)->default(0);

            /** Dato del usuario: días-hombre cotizados ante el IMSS ese mes. */
            $table->decimal('dias_cotizados', 10, 2)->default(0);
            $table->decimal('mo_real', 15, 2)->default(0);

            /**
             * El periodo se acortó y este mes quedó fuera, pero tiene días
             * capturados: se conserva en vez de borrarse.
             */
            $table->boolean('fuera_de_rango')->default(false);
            $table->timestamps();

            $table->unique(['seguimiento_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_icsoe_meses');
    }
};
