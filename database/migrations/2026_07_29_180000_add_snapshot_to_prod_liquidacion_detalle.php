<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El detalle de liquidación pasa a ser un snapshot completo del renglón.
     *
     * Antes solo congelaba el dinero (precio y kilos) y leía marca, descripción,
     * peso y longitud del concepto vivo, así que editar el catálogo cambiaba
     * órdenes de pago ya cerradas. Sin FK a propósito: el renglón debe
     * sobrevivir al borrado de la pieza.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('obra_id')->nullable()->after('concepto_id');
            $table->string('marca')->nullable()->after('obra_id');
            $table->string('descripcion')->nullable()->after('marca');
            $table->decimal('peso_unitario', 10, 3)->nullable()->after('descripcion');
            $table->integer('longitud')->nullable()->after('peso_unitario');
        });

        DB::table('prod_liquidacion_detalle')
            ->whereNull('marca')
            ->orderBy('id')
            ->chunkById(500, function ($detalles): void {
                foreach ($detalles as $detalle) {
                    $concepto = DB::table('conceptos')->find($detalle->concepto_id);

                    if ($concepto === null) {
                        continue;
                    }

                    DB::table('prod_liquidacion_detalle')
                        ->where('id', $detalle->id)
                        ->update([
                            'obra_id' => $concepto->obra_id,
                            'marca' => $concepto->marca,
                            'descripcion' => $concepto->descripcion,
                            'peso_unitario' => $concepto->peso_unitario,
                            'longitud' => $concepto->longitud,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn(['obra_id', 'marca', 'descripcion', 'peso_unitario', 'longitud']);
        });
    }
};
