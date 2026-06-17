<?php

use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea un Proyecto por cada obra existente (1:1), copiando los campos
     * comerciales y enlazando la obra como base. Idempotente: salta las obras
     * que ya tienen proyecto. La planta queda fuera (no es proyecto comercial).
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            Obra::query()
                ->where('es_planta', false)
                ->whereNull('proyecto_id')
                ->orderBy('id')
                ->chunkById(200, function ($obras): void {
                    foreach ($obras as $obra) {
                        $proyecto = Proyecto::create([
                            'no' => $obra->no,
                            'descripcion' => $obra->descripcion,
                            'cliente_id' => $obra->cliente_id,
                            'tipo_contrato' => $obra->tipo_contrato,
                            'monto' => $obra->monto,
                            'monto_iva' => $obra->monto_iva,
                            'anticipo' => $obra->anticipo,
                            'garantia' => $obra->garantia,
                            'estatus' => $obra->estatus,
                            'activa' => $obra->activa,
                        ]);

                        Obra::query()->whereKey($obra->id)->update([
                            'proyecto_id' => $proyecto->id,
                            'tipo' => 'base',
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Obra::query()->whereNotNull('proyecto_id')->update([
                'proyecto_id' => null,
                'tipo' => 'base',
            ]);
            Proyecto::query()->delete();
        });
    }
};
