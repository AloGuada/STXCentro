<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El renglón liquidado guarda el subproceso igual que guarda el proceso:
     * copiado, sin FK. La orden de pago de una semana cerrada tiene que poder
     * reimprimirse aunque después alguien renombre o apague el subproceso.
     *
     * `precio_kilo_aplicado` pasa a nullable: en un renglón de subproceso no
     * existe un precio por kilo, y un 0 se leería como "tarifa sin capturar".
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('subproceso_id')->nullable()->after('proceso_nombre');
            $table->string('subproceso_nombre')->nullable()->after('subproceso_id');
            $table->decimal('precio_subproceso_aplicado', 10, 2)->nullable()->after('precio_kilo_aplicado');
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->decimal('precio_kilo_aplicado', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn(['subproceso_id', 'subproceso_nombre', 'precio_subproceso_aplicado']);
        });

        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->decimal('precio_kilo_aplicado', 10, 4)->nullable(false)->change();
        });
    }
};
