<?php

namespace App\Services\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Historial de cargos de un presupuesto: cada afectación viva a sus centros de
 * costo (ejercida o apartada), con la OC y las solicitudes de pago aprobadas
 * que la respaldan, de la más reciente a la más antigua.
 *
 * Los cargos cancelados o vencidos no aparecen: ya no pesan en el
 * presupuesto. La OC y la afectación cancelan revirtiendo en el ledger sin
 * tocar sus renglones, así que esos cargos se marcan `revertido` en lugar de
 * esconderse.
 */
class HistorialDeCargos
{
    /** Estatus de solicitud de pago que cuentan como aprobada. */
    private const SP_APROBADAS = [SolicitudPagoEstatus::Aprobada, SolicitudPagoEstatus::Pagada];

    /**
     * @return list<array{
     *     id: int,
     *     fecha: string|null,
     *     centro: array{codigo: string|null, descripcion: string|null},
     *     descripcion: string|null,
     *     afectacion: 'ejercido'|'apartado',
     *     revertido: bool,
     *     monto: float,
     *     moneda: string|null,
     *     monto_origen: float|null,
     *     documento: array{tipo: string, id: int, folio: string|null}|null,
     *     orden_compra: array{id: int, folio: string|null}|null,
     *     solicitudes_pago: list<array{id: int, folio: string|null, estatus: string}>
     * }>
     */
    public function de(Presupuesto $presupuesto): array
    {
        return RubroAfectado::query()
            ->whereIn('obra_rubro_id', $presupuesto->rubros()->select('id'))
            ->whereIn('estatus', [RubroAfectadoEstatus::Aplicado->value, RubroAfectadoEstatus::Apartado->value])
            ->with([
                'obraRubro.rubro:id,codigo,descripcion',
                'entrada' => fn (MorphTo $morph) => $morph->morphWith([
                    OrdenCompra::class => ['solicitudesPago:id,orden_compra_id,folio,estatus'],
                ]),
            ])
            ->orderByRaw('COALESCE(fecha_aplicacion, created_at) DESC')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RubroAfectado $cargo) => $this->presentar($cargo))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(RubroAfectado $cargo): array
    {
        $entrada = $cargo->entrada;
        $moneda = $cargo->moneda ? strtolower($cargo->moneda) : null;

        return [
            'id' => $cargo->id,
            'fecha' => ($cargo->fecha_aplicacion ?? $cargo->created_at)?->toIso8601String(),
            'centro' => [
                'codigo' => $cargo->obraRubro?->rubro?->codigo,
                'descripcion' => $cargo->obraRubro?->rubro?->descripcion,
            ],
            'descripcion' => $cargo->descripcion,
            'afectacion' => $cargo->estatus === RubroAfectadoEstatus::Apartado ? 'apartado' : 'ejercido',
            'revertido' => $this->documentoCancelado($entrada),
            'monto' => (float) $cargo->monto,
            'moneda' => $moneda,
            'monto_origen' => $moneda !== null && $moneda !== 'mxn' && $cargo->monto_origen !== null
                ? (float) $cargo->monto_origen
                : null,
            'documento' => $this->documento($entrada),
            'orden_compra' => $entrada instanceof OrdenCompra
                ? ['id' => $entrada->id, 'folio' => $entrada->folio]
                : null,
            'solicitudes_pago' => $this->solicitudesAprobadas($entrada),
        ];
    }

    /**
     * El documento que originó el cargo cuando no es una OC ni una solicitud
     * de pago, que ya tienen su propia columna.
     *
     * @return array{tipo: string, id: int, folio: string|null}|null
     */
    private function documento(?Model $entrada): ?array
    {
        return match (true) {
            $entrada instanceof Requisicion => ['tipo' => 'requisicion', 'id' => $entrada->id, 'folio' => $entrada->folio],
            $entrada instanceof AfectacionPresupuestal => ['tipo' => 'afectacion', 'id' => $entrada->id, 'folio' => $entrada->folio],
            default => null,
        };
    }

    /**
     * @return list<array{id: int, folio: string|null, estatus: string}>
     */
    private function solicitudesAprobadas(?Model $entrada): array
    {
        $solicitudes = match (true) {
            $entrada instanceof OrdenCompra => $entrada->solicitudesPago
                ->filter(fn (SolicitudPago $sp) => in_array($sp->estatus, self::SP_APROBADAS, true)),
            // La solicitud sin OC carga al firmarse; mientras tanto sólo aparta.
            $entrada instanceof SolicitudPago => collect([$entrada]),
            default => collect(),
        };

        return $solicitudes
            ->map(fn (SolicitudPago $sp) => ['id' => $sp->id, 'folio' => $sp->folio, 'estatus' => $sp->estatus->value])
            ->values()
            ->all();
    }

    private function documentoCancelado(?Model $entrada): bool
    {
        return ($entrada instanceof OrdenCompra || $entrada instanceof AfectacionPresupuestal)
            && $entrada->estatus->value === 'cancelada';
    }
}
