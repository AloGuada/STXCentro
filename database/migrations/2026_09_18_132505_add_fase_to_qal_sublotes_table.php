<?php

use App\Enums\Qal\FaseTransformacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En qué transformación se inspeccionó la entrega de accesorios.
     *
     * Hasta ahora todo sublote era de 2ª —soldadura— y la fase se daba por
     * sabida: por eso las que ya existen se quedan en 2ª. Un lote de
     * accesorios también se pinta, y esa entrega se revisa por muestreo con
     * los defectos de pintura, no con los de soldadura. Sin esta columna las
     * dos cosas se contarían juntas en el tablero.
     */
    public function up(): void
    {
        Schema::table('qal_sublotes', function (Blueprint $table) {
            $table->string('fase', 4)->default(FaseTransformacion::Segunda->value)->after('lote_id');
            $table->index(['fase', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('qal_sublotes', function (Blueprint $table) {
            $table->dropIndex(['fase', 'fecha']);
            $table->dropColumn('fase');
        });
    }
};
