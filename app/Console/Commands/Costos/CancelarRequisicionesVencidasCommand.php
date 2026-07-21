<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Models\Costos\Requisicion;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelarRequisicionesVencidasCommand extends Command
{
    protected $signature = 'costos:cancelar-requisiciones-vencidas';

    protected $description = 'Cancela requisiciones en pendiente_aprobacion_interno o aprobada que llevan mas de 10 dias sin avanzar.';

    /** Valor por defecto si no hay configuración guardada. */
    public const DIAS_LIMITE = 10;

    public function handle(ApartadoPresupuestal $apartado): int
    {
        $dias = \App\Models\Costos\ConfiguracionCostos::actual()->dias_cancelar_requisicion ?: self::DIAS_LIMITE;
        $limite = now()->subDays($dias);

        $requisiciones = Requisicion::query()
            ->whereIn('estatus', [
                RequisicionEstatus::PendienteAprobacion->value,
                RequisicionEstatus::Aprobada->value,
            ])
            ->where('updated_at', '<=', $limite)
            ->get();

        if ($requisiciones->isEmpty()) {
            $this->info('No hay requisiciones vencidas para cancelar.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($requisiciones as $requisicion) {
            DB::transaction(function () use ($requisicion, $apartado, &$count) {
                $requisicion->cadenaAprobacion()
                    ->where('estatus', 'pendiente')
                    ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);

                $apartado->cancelarApartadosDe($requisicion, 'cancelada por vencimiento (10 dias sin actividad)');

                $requisicion->transitionTo(RequisicionEstatus::Cancelada);

                $requisicion->update([
                    'motivo_rechazo' => 'Cancelada automaticamente por sistema: requisicion sin actividad durante 10 dias.',
                ]);

                activity('costos')
                    ->performedOn($requisicion)
                    ->withProperties(['motivo' => 'Vencimiento por inactividad (10 dias)'])
                    ->log('Requisicion cancelada automaticamente por vencimiento');

                $count++;
            });
        }

        $this->info("Canceladas {$count} requisicion(es) vencida(s).");

        return self::SUCCESS;
    }
}
