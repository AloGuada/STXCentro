<?php

use App\Enums\Prod\TipoPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El grupo de precios declara con qué regla paga. Todo lo que existe hoy
     * cobra por kilo, así que ese es el default y nada cambia de importe.
     */
    public function up(): void
    {
        Schema::table('prod_grupos_precio', function (Blueprint $table) {
            $table->string('tipo_pago', 20)->default(TipoPago::Kilo->value)->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('prod_grupos_precio', function (Blueprint $table) {
            $table->dropColumn('tipo_pago');
        });
    }
};
