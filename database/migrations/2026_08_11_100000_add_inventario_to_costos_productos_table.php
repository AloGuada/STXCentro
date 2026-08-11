<?php

use App\Enums\Alm\ProductoTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prepara el catálogo compartido para el kardex.
     *
     * `costos_productos` lo administran Compras y Almacén sobre la misma tabla:
     * el mismo código sirve para cotizar y para llevar existencias. Lo que
     * faltaba era decirle a Almacén cómo se comporta cada artículo.
     *
     * - `tipo` separa lo que se consume de lo que se presta y regresa.
     * - `controla_inventario` en false saca al producto del kardex: fletes,
     *   maniobras y servicios se compran pero no se almacenan.
     * - `se_controla_por_pieza` marca lo que además lleva número de serie y
     *   resguardo por persona. Sin esto un kardex por cantidad sabe cuántas
     *   pulidoras salieron, pero no quién tiene cuál.
     */
    public function up(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->string('tipo', 20)->default(ProductoTipo::Insumo->value)->after('unidad');
            $table->boolean('controla_inventario')->default(true)->after('tipo');
            $table->boolean('se_controla_por_pieza')->default(false)->after('controla_inventario');
            $table->boolean('requiere_verificacion')->default(false)->after('se_controla_por_pieza');
            $table->decimal('stock_minimo', 14, 3)->nullable()->after('requiere_verificacion');
            $table->string('imagen')->nullable()->after('stock_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'controla_inventario',
                'se_controla_por_pieza',
                'requiere_verificacion',
                'stock_minimo',
                'imagen',
            ]);
        });
    }
};
