<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La junta a la que pertenece cada cordón, y los barrenos de cada marca.
 *
 * El servicio de IFC ahora agrupa los cordones del mismo par de piezas que se
 * tocan: eso es lo que en el plano lleva un solo símbolo de soldadura. La
 * captura del inspector no cambia —sigue siendo por cordón—, pero el número de
 * junta se guarda para poder referenciarla en los formatos de segunda y PND.
 *
 * `remate` marca el tramo corto que da la vuelta por la punta: es parte de la
 * junta, no una soldadura aparte, y no manda en los catetos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qal_modelo_cordones', function (Blueprint $table): void {
            $table->unsignedInteger('junta_id')->nullable()->after('numero');
            $table->boolean('remate')->default(false)->after('junta');
        });

        Schema::table('qal_modelo_marcas', function (Blueprint $table): void {
            $table->unsignedInteger('juntas')->default(0)->after('soldaduras');
            $table->unsignedInteger('orificios')->default(0)->after('juntas');
        });
    }

    public function down(): void
    {
        Schema::table('qal_modelo_cordones', function (Blueprint $table): void {
            $table->dropColumn(['junta_id', 'remate']);
        });

        Schema::table('qal_modelo_marcas', function (Blueprint $table): void {
            $table->dropColumn(['juntas', 'orificios']);
        });
    }
};
