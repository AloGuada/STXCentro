<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El método de la prueba de adherencia baja de la prueba a cada tira.
     *
     * En ASTM D3359 el método depende del espesor de la película: A (en cruz)
     * por encima de 5 mils y B (cuadrícula) por debajo. Las tres tiras de una
     * pieza no se cortan siempre en el mismo sitio ni sobre el mismo espesor,
     * así que cada una puede llevar el suyo. Guardar uno solo para las tres
     * obligaba a mentir en la que no coincidía.
     *
     * Lo ya capturado se conserva: cada tira hereda el método que tenía su
     * prueba, que es el que se le aplicó.
     */
    public function up(): void
    {
        Schema::table('qal_adherencia_tiras', function (Blueprint $table) {
            $table->string('metodo', 1)->nullable()->after('orden');
        });

        DB::statement('
            UPDATE qal_adherencia_tiras
            SET metodo = (SELECT metodo FROM qal_adherencia WHERE qal_adherencia.id = qal_adherencia_tiras.adherencia_id)
        ');

        Schema::table('qal_adherencia_tiras', function (Blueprint $table) {
            $table->string('metodo', 1)->nullable(false)->change();
        });

        Schema::table('qal_adherencia', function (Blueprint $table) {
            $table->dropColumn('metodo');
        });
    }

    public function down(): void
    {
        Schema::table('qal_adherencia', function (Blueprint $table) {
            $table->string('metodo', 1)->nullable();
        });

        // Al volver atrás la prueba se queda con el método de su primera tira:
        // es la única forma de meter tres valores en una sola columna.
        DB::statement('
            UPDATE qal_adherencia
            SET metodo = (
                SELECT metodo FROM qal_adherencia_tiras
                WHERE qal_adherencia_tiras.adherencia_id = qal_adherencia.id
                ORDER BY orden
                LIMIT 1
            )
        ');

        Schema::table('qal_adherencia_tiras', function (Blueprint $table) {
            $table->dropColumn('metodo');
        });
    }
};
