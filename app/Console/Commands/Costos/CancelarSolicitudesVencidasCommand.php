<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelarSolicitudesVencidasCommand extends Command
{
    protected $signature = 'costos:cancelar-solicitudes-vencidas';

    protected $description = 'Cancela solicitudes de pago en pendiente_firma (no aprobadas) que llevan demasiados dias sin avanzar.';

    /** Valor por defecto si no hay configuración guardada. */
    public const DIAS_LIMITE = 10;

    public function handle(ApartadoPresupuestal $apartado): int
    {
        $dias = ConfiguracionCostos::actual()->dias_cancelar_solicitud ?: self::DIAS_LIMITE;
        $limite = now()->subDays($dias);

        $solicitudes = SolicitudPago::query()
            ->where('estatus', SolicitudPagoEstatus::PendienteFirma->value)
            ->where('updated_at', '<=', $limite)
            ->get();

        if ($solicitudes->isEmpty()) {
            $this->info('No hay solicitudes de pago vencidas para cancelar.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($solicitudes as $solicitud) {
            DB::transaction(function () use ($solicitud, $apartado, &$count, $dias) {
                $solicitud->cadenaAprobacion()
                    ->where('estatus', 'pendiente')
                    ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);

                $apartado->cancelarApartadosDe($solicitud, "cancelada por vencimiento ({$dias} dias sin firma)");

                $solicitud->transitionTo(SolicitudPagoEstatus::Cancelada);

                activity('costos')
                    ->performedOn($solicitud)
                    ->withProperties(['motivo' => "Vencimiento por inactividad ({$dias} dias)"])
                    ->log('Solicitud de pago cancelada automaticamente por vencimiento');

                $count++;
            });
        }

        $this->info("Canceladas {$count} solicitud(es) de pago vencida(s).");

        return self::SUCCESS;
    }
}
