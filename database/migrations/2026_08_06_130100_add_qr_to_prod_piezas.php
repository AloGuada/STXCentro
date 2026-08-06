<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El QR pasa a ser el identificador de la pieza física.
     *
     * El layout nuevo trae una columna QR por renglón y el QS deja de ser único:
     * el mismo QS puede repetirse entre lotes. Por eso el unique se muda de
     * `(catálogo, QS)` a `(catálogo, QR)` y el QS se queda como dato de consulta
     * con índice normal.
     *
     * Las piezas que ya existen se quedan con `qr = qs`: hasta hoy el QS era el
     * identificador, así que arrancan con el mismo valor y el import las vuelve
     * a emparejar sin duplicarlas.
     */
    public function up(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->string('qr', 100)->nullable()->after('concepto_id');
        });

        DB::table('prod_piezas')->whereNull('qr')->update(['qr' => DB::raw('qs')]);

        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->dropUnique(['catalogo_id', 'qs']);
        });

        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->unique(['catalogo_id', 'qr']);
            $table->index(['catalogo_id', 'qs']);
        });

        // El snapshot de la liquidación congela el QR por la misma razón que
        // congela el QS: si la pieza se borra, es lo único que ata lo pagado a
        // una pieza concreta, y el QS ya no sirve porque se repite entre lotes.
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->string('qr', 100)->nullable()->after('pieza_id');
        });

        DB::table('prod_liquidacion_detalle')->whereNull('qr')->update([
            'qr' => DB::table('prod_piezas')
                ->select('qr')
                ->whereColumn('prod_piezas.id', 'prod_liquidacion_detalle.pieza_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn('qr');
        });

        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->dropUnique(['catalogo_id', 'qr']);
            $table->dropIndex(['catalogo_id', 'qs']);
        });

        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->dropColumn('qr');
            $table->unique(['catalogo_id', 'qs']);
        });
    }
};
