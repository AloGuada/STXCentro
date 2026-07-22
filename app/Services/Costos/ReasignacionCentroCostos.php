<?php

namespace App\Services\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reasigna los centros de costos de un documento ya aprobado sin re-firmar:
 * revierte los cargos aplicados vivos (devolviéndolos al acumulado del rubro
 * viejo y marcándolos Cancelado), reemplaza los detalles con el set nuevo y
 * aplica el impacto sobre los rubros nuevos (Aplicado, permitiendo sobregiro).
 * Todo en una transacción y con bitácora del motivo.
 *
 * Por ahora opera sobre SolicitudPago; la mecánica (reverso + reaplicación vía
 * las primitivas compartidas) es generalizable a Afectación y OC.
 */
class ReasignacionCentroCostos
{
    public function __construct(
        private readonly AcumuladoLedger $ledger,
        private readonly ApartadoPresupuestal $apartado,
    ) {}

    /**
     * @param  array<int, array{obra_rubro_id: int|string, monto: float|string, concepto?: string|null}>  $nuevosDetalles
     */
    public function reasignar(SolicitudPago $solicitud, array $nuevosDetalles, string $motivo, ?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        abort_unless(
            $solicitud->puedeReasignarCentroCostos(),
            422,
            'La solicitud no admite reasignación de centros de costos.',
        );

        $sumaNueva = round(array_sum(array_map(fn (array $d): float => (float) $d['monto'], $nuevosDetalles)), 2);

        // En una solicitud pagada solo se puede redistribuir el gasto entre
        // centros de costos: el total ya pagado no cambia.
        if ($solicitud->estatus === SolicitudPagoEstatus::Pagada) {
            $epsilon = (float) config('costos.epsilon_monto', 0.01);
            abort_if(
                abs($sumaNueva - (float) $solicitud->monto_total) > $epsilon,
                422,
                'En una solicitud pagada la suma de los centros de costos debe igualar el monto pagado.',
            );
        }

        DB::transaction(function () use ($solicitud, $nuevosDetalles, $motivo, $userId, $sumaNueva): void {
            $antes = $solicitud->detalles()
                ->get()
                ->map(fn ($d): array => [
                    'obra_rubro_id' => (int) $d->obra_rubro_id,
                    'monto' => (float) $d->subtotal,
                    'concepto' => $d->concepto,
                ])
                ->all();

            // 1. Revertir los cargos aplicados vivos de esta entrada: devolver el
            // monto al acumulado del rubro y marcar el RubroAfectado Cancelado.
            $solicitud->rubrosAfectados()
                ->where('estatus', RubroAfectadoEstatus::Aplicado->value)
                ->get()
                ->each(function (RubroAfectado $ra) use ($motivo, $userId): void {
                    $this->ledger->registrarPorId($ra->obra_rubro_id, -(float) $ra->monto, $ra->id, $motivo, $userId);
                    $ra->update([
                        'estatus' => RubroAfectadoEstatus::Cancelado,
                        'descripcion' => trim(($ra->descripcion ? $ra->descripcion.' · ' : '').$motivo),
                    ]);
                });

            // 2. Reemplazar los detalles con el set nuevo.
            $solicitud->detalles()->delete();

            foreach ($nuevosDetalles as $detalle) {
                $obraRubro = ObraRubro::find($detalle['obra_rubro_id']);
                $monto = round((float) $detalle['monto'], 2);

                $solicitud->detalles()->create([
                    'obra_rubro_id' => $detalle['obra_rubro_id'],
                    'sobre_obra_cerrada' => $obraRubro?->estaCerrado() ?? false,
                    'concepto' => $detalle['concepto'] ?? $solicitud->concepto,
                    'cantidad' => 1,
                    'precio_unitario' => $monto,
                    'subtotal' => $monto,
                ]);
            }

            // 3. Aplicar el impacto sobre los rubros nuevos (Aplicado, con sobregiro).
            $solicitud->load('detalles');
            foreach ($solicitud->detalles as $detalle) {
                $this->apartado->aplicarCargo(
                    entrada: $solicitud,
                    obraRubroId: (int) $detalle->obra_rubro_id,
                    monto: (float) $detalle->subtotal,
                    estatus: RubroAfectadoEstatus::Aplicado,
                    descripcion: $detalle->concepto,
                    userId: $userId,
                    allowSobregiro: true,
                );
            }

            // 4. Recalcular el total salvo en pagada (donde queda fijo).
            if ($solicitud->estatus !== SolicitudPagoEstatus::Pagada) {
                $solicitud->update(['monto_total' => $sumaNueva]);
            }

            // 5. Bitácora de la reasignación.
            activity('costos')
                ->performedOn($solicitud)
                ->causedBy($userId ? Usuario::find($userId) : null)
                ->withProperties([
                    'motivo' => $motivo,
                    'antes' => $antes,
                    'despues' => array_map(fn (array $d): array => [
                        'obra_rubro_id' => (int) $d['obra_rubro_id'],
                        'monto' => round((float) $d['monto'], 2),
                        'concepto' => $d['concepto'] ?? null,
                    ], $nuevosDetalles),
                ])
                ->log('Centros de costos reasignados');
        });
    }
}
