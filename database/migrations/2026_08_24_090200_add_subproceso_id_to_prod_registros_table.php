<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué subproceso se hizo. Nulo en la producción que se paga por kilo, que
     * es toda la que existe hasta hoy.
     *
     * Es `restrictOnDelete` porque el subproceso es quien pone el precio: si se
     * pudiera borrar con producción capturada, el renglón quedaría sin importe
     * y sin forma de reconstruirlo. Un subproceso que ya no se usa se apaga con
     * `activo`, no se borra.
     */
    public function up(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->foreignId('subproceso_id')
                ->nullable()
                ->after('proceso_id')
                ->constrained('prod_grupo_precio_subprocesos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subproceso_id');
        });
    }
};
