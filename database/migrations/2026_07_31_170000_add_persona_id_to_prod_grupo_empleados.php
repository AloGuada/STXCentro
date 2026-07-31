<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El integrante del grupo deja de ser texto suelto y apunta a la persona de
     * RH. Se engancha a `rh_personas` y no al periodo laboral porque el grupo es
     * permanente y el periodo se cierra en cada baja: el periodo vigente se
     * resuelve a la fecha en que se necesita.
     *
     * `nombre` y `no_empleado` se quedan como copia visible (y como único dato
     * de los renglones viejos que no crucen contra RH).
     */
    public function up(): void
    {
        Schema::table('prod_grupo_empleados', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('grupo_trabajo_id')
                ->constrained('rh_personas')->nullOnDelete();
        });

        $this->cruzarPorNumeroDeEmpleado();
    }

    public function down(): void
    {
        Schema::table('prod_grupo_empleados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });
    }

    /**
     * Amarra lo ya capturado buscando su número de empleado en los periodos
     * laborales. Lo que no cruce se queda en null y se resuelve a mano.
     */
    private function cruzarPorNumeroDeEmpleado(): void
    {
        $personaPorNumero = DB::table('rh_periodos_laborales')
            ->whereNotNull('numero_empleado')
            ->orderBy('id')
            ->pluck('persona_id', 'numero_empleado');

        if ($personaPorNumero->isEmpty()) {
            return;
        }

        $empleados = DB::table('prod_grupo_empleados')
            ->whereNotNull('no_empleado')
            ->get(['id', 'no_empleado']);

        foreach ($empleados as $empleado) {
            $personaId = $personaPorNumero[trim($empleado->no_empleado)] ?? null;

            if ($personaId !== null) {
                DB::table('prod_grupo_empleados')
                    ->where('id', $empleado->id)
                    ->update(['persona_id' => $personaId]);
            }
        }
    }
};
