<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A qué obra pertenece el material que movió este asiento. `null` = libre,
     * sin dueño.
     *
     * Es lo que vuelve **derivable** la partición de `alm_asignaciones`, igual
     * que el resto del kardex vuelve derivable el saldo de `alm_existencias`.
     * Sin esta columna la partición sería un número sin historia: se sabría
     * cuánto tiene comprometido cada obra, pero no de dónde salió ni cómo
     * revertirlo.
     *
     * De aquí sale la regla del asiento por origen: una salida que toma 20 de su
     * obra y 10 de lo libre deja **dos** movimientos, no uno con la suma. Sólo
     * así la cancelación puede devolver cada parte a donde estaba sin adivinar.
     *
     * `nullOnDelete` y no `restrict`: si alguna vez se depura una obra, el
     * asiento del kardex no debe irse con ella —igual que `activo_id`—; lo que
     * se pierde es de quién era, no que el material se movió.
     */
    public function up(): void
    {
        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('producto_id')
                ->constrained('obras')->nullOnDelete();

            // Reconstruir la partición de una existencia: su corrido por obra.
            $table->index(['existencia_id', 'obra_id']);
        });
    }

    public function down(): void
    {
        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->dropIndex(['existencia_id', 'obra_id']);
            $table->dropConstrainedForeignId('obra_id');
        });
    }
};
