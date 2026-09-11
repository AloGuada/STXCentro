<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La junta de PND apunta a la marca de Producción (`conceptos`) y deja de
     * apuntar a `qal_piezas`, el padrón propio de la aplicación de mapeo 2D que
     * se retira.
     *
     * `marca` sigue guardando el texto del laboratorio: el enlace es un extra,
     * no el dato. Las juntas que ya existen se enganchan con la misma regla que
     * el servicio: la marca, si es única en el catálogo vigente de la obra. Una
     * marca repetida en dos lotes no se adivina y queda suelta.
     */
    public function up(): void
    {
        Schema::table('qal_pnd_juntas', function (Blueprint $table) {
            $table->foreignId('concepto_id')->nullable()->after('qal_pnd_reporte_id')->constrained('conceptos')->nullOnDelete();
        });

        $this->engancharLasQueYaExisten();

        Schema::table('qal_pnd_juntas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qal_pieza_id');
        });
    }

    /**
     * Vuelve la columna vieja, vacía: el enlace a la pieza del mapeo 2D no se
     * puede reconstruir desde la marca de Producción.
     */
    public function down(): void
    {
        Schema::table('qal_pnd_juntas', function (Blueprint $table) {
            $table->foreignId('qal_pieza_id')->nullable()->after('qal_pnd_reporte_id')->constrained('qal_piezas')->nullOnDelete();
        });

        Schema::table('qal_pnd_juntas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concepto_id');
        });
    }

    private function engancharLasQueYaExisten(): void
    {
        $obraDelReporte = DB::table('qal_pnd_reportes')
            ->join('qal_obras', 'qal_obras.id', '=', 'qal_pnd_reportes.qal_obra_id')
            ->pluck('qal_obras.obra_id', 'qal_pnd_reportes.id');

        $marcasPorObra = [];

        foreach ($obraDelReporte as $reporteId => $obraId) {
            $marcas = $marcasPorObra[$obraId] ??= $this->marcasUnicas((int) $obraId);

            DB::table('qal_pnd_juntas')
                ->where('qal_pnd_reporte_id', $reporteId)
                ->whereNull('concepto_id')
                ->get(['id', 'marca'])
                ->each(function (object $junta) use ($marcas): void {
                    $conceptoId = $marcas->get(mb_strtoupper(trim((string) $junta->marca)));

                    if ($conceptoId !== null) {
                        DB::table('qal_pnd_juntas')->where('id', $junta->id)->update(['concepto_id' => $conceptoId]);
                    }
                });
        }
    }

    /**
     * @return Collection<string, int> marca en mayúsculas → id de la marca de Producción
     */
    private function marcasUnicas(int $obraId): Collection
    {
        return DB::table('conceptos')
            ->join('prod_catalogos', 'prod_catalogos.id', '=', 'conceptos.catalogo_id')
            ->where('prod_catalogos.vigente', true)
            ->where('conceptos.obra_id', $obraId)
            ->get(['conceptos.id', 'conceptos.marca'])
            ->groupBy(fn (object $concepto): string => mb_strtoupper(trim((string) $concepto->marca)))
            ->filter(fn (Collection $iguales): bool => $iguales->count() === 1)
            ->map(fn (Collection $iguales): int => (int) $iguales->first()->id);
    }
};
