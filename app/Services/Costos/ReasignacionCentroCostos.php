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
 * Lo que NO toca es el monto de la solicitud: reasignar es mover el gasto de
 * un centro de costos a otro, no volver a cotizar lo que se va a pagar. El
 * dinero ya se firmó con ese importe, y recalcularlo desde el desglose
 * cambiaría lo que se le debe al proveedor por corregir una captura.
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

        // En una solicitud pagada el desglose además tiene que cuadrar con lo
        // que ya salió del banco: ahí no hay margen para que el reparto sume
        // distinto. En las demás sólo se avisa en pantalla.
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
            // El desglose viene en la divisa de la solicitud y el presupuesto
            // pesa en MXN: hay que reaplicar con la misma moneda y el mismo tipo
            // de cambio con que se firmó, o el cargo se encogería al valor
            // nominal en divisa (mover el gasto de centro no lo re-cotiza).
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
                    moneda: $solicitud->tipo_moneda ?? 'mxn',
                    tc: $solicitud->tipo_cambio ? (float) $solicitud->tipo_cambio : null,
                );
            }

            // 4. Bitácora de la reasignación. Se guarda la suma junto al monto
            // de la solicitud: cuando no coinciden, es lo que explica por qué el
            // presupuesto cargado no es igual a lo que se pagó.
            activity('costos')
                ->performedOn($solicitud)
                ->causedBy($userId ? Usuario::find($userId) : null)
                ->withProperties([
                    'motivo' => $motivo,
                    'monto_total' => round((float) $solicitud->monto_total, 2),
                    'suma_detalles' => $sumaNueva,
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
