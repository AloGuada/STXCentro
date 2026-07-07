<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Siembra en cada presupuesto de ámbito 'obra' (proyecto, obra y partida; la
 * planta queda fuera) todos los centros de costo de tipo 'obra' que le falten,
 * con `presupuestado = 0`. Reutiliza {@see Presupuesto::sembrarRubrosFaltantes()}.
 *
 * Por defecto corre en modo dry-run (solo reporta). Requiere --force para sembrar.
 */
class SembrarRubrosObraCommand extends Command
{
    protected $signature = 'costos:sembrar-rubros-obra
        {--force : Ejecuta la siembra; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Agrega a cada presupuesto de obra los centros de costo de tipo obra que le falten';

    public function handle(): int
    {
        $rubroIdsObra = Rubro::query()->where('ambito', 'obra')->pluck('id');

        if ($rubroIdsObra->isEmpty()) {
            $this->warn('No hay centros de costo de tipo obra registrados.');

            return self::SUCCESS;
        }

        $presupuestos = Presupuesto::query()
            ->with('presupuestable')
            ->get()
            ->filter(fn (Presupuesto $p) => $p->ambitoRubros() === 'obra');

        if ($presupuestos->isEmpty()) {
            $this->warn('No hay presupuestos de ámbito obra.');

            return self::SUCCESS;
        }

        $filas = [];
        $totalFaltantes = 0;

        foreach ($presupuestos as $presupuesto) {
            $asignados = $presupuesto->rubros()->pluck('rubro_id');
            $faltantes = $rubroIdsObra->diff($asignados)->count();

            if ($faltantes === 0) {
                continue;
            }

            $filas[] = [$presupuesto->nombreMostrar(), (string) $faltantes];
            $totalFaltantes += $faltantes;
        }

        if ($totalFaltantes === 0) {
            $this->info('Todos los presupuestos de obra ya tienen los '.$rubroIdsObra->count().' centros de costo. Nada que hacer.');

            return self::SUCCESS;
        }

        $this->info("Presupuestos de obra a completar: {$presupuestos->count()} (con faltantes: ".count($filas).')');
        $this->newLine();
        $this->table(['Presupuesto', 'Centros faltantes'], $filas);

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn("DRY-RUN: no se sembró nada. Se crearían {$totalFaltantes} centros de costo.");
            $this->line('Vuelve a ejecutar con --force para sembrar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($presupuestos): void {
            foreach ($presupuestos as $presupuesto) {
                $presupuesto->sembrarRubrosFaltantes();
            }
        });

        $this->newLine();
        $this->info("Listo: {$totalFaltantes} centros de costo sembrados en ".count($filas).' presupuestos.');

        return self::SUCCESS;
    }
}
