<?php

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Asigna a cada estimación el proyecto de su obra (1:1 tras el backfill de
     * proyectos). Idempotente: solo toca las que aún no tienen proyecto.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            Estimacion::query()
                ->whereNull('proyecto_id')
                ->whereNotNull('obra_id')
                ->orderBy('id')
                ->chunkById(500, function ($estimaciones): void {
                    foreach ($estimaciones as $estimacion) {
                        $proyectoId = Obra::query()->whereKey($estimacion->obra_id)->value('proyecto_id');
                        if ($proyectoId !== null) {
                            Estimacion::query()->whereKey($estimacion->id)->update(['proyecto_id' => $proyectoId]);
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        Estimacion::query()->update(['proyecto_id' => null]);
    }
};
