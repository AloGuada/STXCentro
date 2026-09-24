<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El plan de una semana nace abierto —un borrador que Producción arma y
     * corrige— y no cuenta ni lo ve Calidad hasta que se cierra. Cerrado es el
     * compromiso de la semana: ya no se toca, y lo que no se fabrique se
     * arrastra.
     */
    public function up(): void
    {
        Schema::table('qal_programaciones', function (Blueprint $table) {
            $table->timestamp('cerrada_at')->nullable()->after('capturista_id');
            $table->foreignUuid('cerrada_por_id')->nullable()->after('cerrada_at')->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qal_programaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrada_por_id');
            $table->dropColumn('cerrada_at');
        });
    }
};
