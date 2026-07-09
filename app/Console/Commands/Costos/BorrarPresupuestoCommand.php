<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\RubroAfectado;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Elimina uno o más presupuestos de Costos por id junto con sus centros de costos
 * (costos_obra_rubros, que cascadean al borrar el presupuesto).
 *
 * El único FK que bloquea el borrado es costos_rubros_afectados (restrict): se
 * eliminan antes. El resto de referencias a obra_rubro cascadean (detalle de
 * solicitud/afectación) o se ponen a null (detalle de OC/requisición, selección).
 *
 * Por defecto corre en dry-run (solo reporta). Requiere --force para borrar.
 */
class BorrarPresupuestoCommand extends Command
{
    protected $signature = 'costos:borrar-presupuesto
        {id* : ID(s) del presupuesto a eliminar}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Elimina presupuestos de Costos por id con sus centros de costos y rubros afectados';

    public function handle(): int
    {
        $ids = collect($this->argument('id'))->map(fn ($id): int => (int) $id)->unique();

        $presupuestos = Presupuesto::query()->whereIn('id', $ids)->get();

        $faltantes = $ids->diff($presupuestos->pluck('id'));
        if ($faltantes->isNotEmpty()) {
            $this->error('No existe presupuesto con id: '.$faltantes->implode(', '));

            return self::FAILURE;
        }

        $rubroIds = ObraRubro::query()->whereIn('presupuesto_id', $ids)->pluck('id');
        $plan = $this->construirPlan($ids, $rubroIds);

        foreach ($presupuestos as $presupuesto) {
            $this->line("• Presupuesto {$presupuesto->id}: {$presupuesto->nombre_mostrar} (OP {$presupuesto->op_mostrar}, estatus {$presupuesto->estatus->value})");
        }
        $this->newLine();
        $this->table(
            ['Tabla / entidad', 'Registros'],
            collect($plan)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids, $rubroIds): void {
            RubroAfectado::query()->whereIn('obra_rubro_id', $rubroIds)->delete();
            Presupuesto::query()->whereIn('id', $ids)->delete();
        });

        $this->newLine();
        $this->info("Eliminados {$presupuestos->count()} presupuesto(s) y sus centros de costos.");

        return self::SUCCESS;
    }

    /**
     * Conteo por tabla afectada para el reporte.
     *
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, int>  $rubroIds
     * @return array<string, int>
     */
    private function construirPlan(Collection $ids, Collection $rubroIds): array
    {
        $afectados = RubroAfectado::query()->whereIn('obra_rubro_id', $rubroIds);
        $vivos = (clone $afectados)
            ->whereIn('estatus', collect(RubroAfectadoEstatus::activos())->map->value)
            ->count();

        return array_filter([
            'Presupuestos' => $ids->count(),
            'Centros de costos (obra_rubros)' => $rubroIds->count(),
            'Rubros afectados (se borran)' => (clone $afectados)->count(),
            '  vivos (apartado/aplicado)' => $vivos,
            'Detalles de solicitud (cascade)' => DB::table('costos_solicitudes_pago_detalle')->whereIn('obra_rubro_id', $rubroIds)->count(),
            'Detalles de afectacion (cascade)' => DB::table('costos_afectaciones_detalle')->whereIn('obra_rubro_id', $rubroIds)->count(),
            'Detalles de OC (obra_rubro -> null)' => DB::table('costos_ordenes_compra_detalle')->whereIn('obra_rubro_id', $rubroIds)->count(),
            'Detalles de requisicion (obra_rubro -> null)' => DB::table('costos_requisicion_detalle')->whereIn('obra_rubro_id', $rubroIds)->count(),
            'Selecciones de requisicion (obra_rubro -> null)' => DB::table('costos_requisicion_seleccion')->whereIn('obra_rubro_id', $rubroIds)->count(),
        ], fn (int $n): bool => $n > 0);
    }
}
