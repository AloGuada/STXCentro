<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Linaje entre versiones del catálogo: cada pieza copiada recuerda de cuál
     * viene.
     *
     * Sin esto, el acumulado de lo pagado se empata por el texto de la marca y
     * renombrar una pieza al versionar reinicia el conteo, permitiendo volver a
     * pagar lo ya pagado.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->foreignId('concepto_origen_id')->nullable()->after('catalogo_id')
                ->constrained('conceptos')->nullOnDelete();
        });

        // Copias hechas antes de este cambio: se emparejan con su version
        // anterior por marca, que es como se contaban hasta ahora.
        DB::table('prod_catalogos')
            ->whereNotNull('catalogo_origen_id')
            ->orderBy('version')
            ->each(function ($catalogo): void {
                DB::table('conceptos')
                    ->where('catalogo_id', $catalogo->id)
                    ->whereNull('concepto_origen_id')
                    ->orderBy('id')
                    ->each(function ($concepto) use ($catalogo): void {
                        $origen = DB::table('conceptos')
                            ->where('catalogo_id', $catalogo->catalogo_origen_id)
                            ->where('marca', $concepto->marca)
                            ->first();

                        if ($origen !== null) {
                            DB::table('conceptos')
                                ->where('id', $concepto->id)
                                ->update(['concepto_origen_id' => $origen->id]);
                        }
                    });
            });
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concepto_origen_id');
        });
    }
};
