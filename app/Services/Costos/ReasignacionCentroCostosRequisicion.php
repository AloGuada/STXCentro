<?php

namespace App\Services\Costos;

use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mueve una requisición de un centro de costos a otro después de los hechos,
 * con todo lo que heredó de ella: sus partidas, las de sus órdenes de compra,
 * los renglones de las solicitudes de pago de contado y los cargos que esos
 * documentos tienen vivos en el presupuesto.
 *
 * El destino se da como un mapa `obra_rubro` viejo → nuevo. Lo que no está en
 * el mapa se queda donde está.
 *
 * Los cargos se mueven **tal como están**, uno por uno, en vez de recalcularse
 * desde las partidas: el cargo de una orden ya trae los ajustes de precio de la
 * recepción, las cancelaciones de unidades y lo que corrigió la conciliación de
 * tipo de cambio, y nada de eso sale de la partida. Cada cargo se revierte en
 * su rubro y se vuelve a aplicar en el destino con el mismo monto en pesos, la
 * misma divisa y el mismo estatus (un apartado sigue siendo apartado).
 *
 * Se niega si alguna orden viva ya tiene recepciones: el material quedó
 * asignado en almacén a la obra vieja y mover sólo el gasto dejaría el kardex
 * contando otra historia. Las órdenes canceladas se saltan: su reversa vieja
 * no marca los cargos como cancelados y moverlos los revertiría dos veces.
 *
 * Un presupuesto destino cerrado o sobregirado no detiene nada; el plan lo
 * avisa y la requisición queda marcada `sobre_obra_cerrada`.
 */
class ReasignacionCentroCostosRequisicion
{
    public function __construct(
        private readonly AcumuladoLedger $ledger,
        private readonly ApartadoPresupuestal $apartado,
    ) {}

    /**
     * Qué haría la reasignación, sin tocar nada.
     *
     * @param  array<int, int>  $mapa  obra_rubro_id viejo => obra_rubro_id nuevo
     * @return array{
     *     errores: list<string>,
     *     avisos: list<string>,
     *     partidas: list<array{documento: string, partida: string, de: string, a: string}>,
     *     cargos: list<array{documento: string, estatus: string, monto: float, de: string, a: string}>,
     *     ocs_omitidas: list<string>,
     * }
     */
    public function planear(Requisicion $requisicion, array $mapa): array
    {
        $plan = ['errores' => [], 'avisos' => [], 'partidas' => [], 'cargos' => [], 'ocs_omitidas' => []];

        $rubros = ObraRubro::query()
            ->with(['presupuesto.presupuestable', 'rubro:id,codigo,descripcion'])
            ->whereIn('id', array_merge(array_keys($mapa), array_values($mapa)))
            ->get()
            ->keyBy('id');

        $plan['errores'] = $this->errores($requisicion, $mapa, $rubros);

        if ($plan['errores'] !== []) {
            return $plan;
        }

        $etiqueta = fn (int $id): string => $this->etiqueta($rubros->get($id));
        $ocsVivas = $this->ocsVivas($requisicion);

        $plan['ocs_omitidas'] = $requisicion->ordenesGeneradas()
            ->where('estatus', OrdenCompraEstatus::Cancelada->value)
            ->pluck('folio')
            ->all();

        foreach ($this->partidas($requisicion, $ocsVivas, $mapa) as [$documento, $partida, $rubroId]) {
            $plan['partidas'][] = [
                'documento' => $documento,
                'partida' => $partida,
                'de' => $etiqueta($rubroId),
                'a' => $etiqueta($mapa[$rubroId]),
            ];
        }

        $cargos = $this->cargos($requisicion, $ocsVivas, $mapa);

        foreach ($cargos as $cargo) {
            $plan['cargos'][] = [
                'documento' => $this->documento($cargo),
                'estatus' => $cargo->estatus->value,
                'monto' => (float) $cargo->monto,
                'de' => $etiqueta($cargo->obra_rubro_id),
                'a' => $etiqueta($mapa[$cargo->obra_rubro_id]),
            ];
        }

        foreach (array_unique(array_values($mapa)) as $destinoId) {
            $destino = $rubros->get($destinoId);
            $entra = (float) $cargos->filter(fn (RubroAfectado $c): bool => $mapa[$c->obra_rubro_id] === $destinoId)->sum('monto');

            if ($destino->estaCerrado()) {
                $plan['avisos'][] = "El presupuesto de {$etiqueta($destinoId)} está cerrado.";
            }

            if ($entra > 0 && (float) $destino->disponible - $entra < 0) {
                $plan['avisos'][] = sprintf(
                    '%s queda sobregirado: disponible $%s, entran $%s.',
                    $etiqueta($destinoId),
                    number_format((float) $destino->disponible, 2),
                    number_format($entra, 2),
                );
            }
        }

        return $plan;
    }

    /**
     * Ejecuta la reasignación en una sola transacción. Quien llama ya revisó
     * el plan: si hay errores, no se hace nada.
     *
     * @param  array<int, int>  $mapa  obra_rubro_id viejo => obra_rubro_id nuevo
     * @return array{partidas: int, cargos: int}
     */
    public function reasignar(Requisicion $requisicion, array $mapa, string $motivo, ?string $userId = null): array
    {
        $plan = $this->planear($requisicion, $mapa);

        if ($plan['errores'] !== []) {
            throw new \RuntimeException(implode(' ', $plan['errores']));
        }

        return DB::transaction(function () use ($requisicion, $mapa, $motivo, $userId, $plan): array {
            $ocsVivas = $this->ocsVivas($requisicion);
            $cargos = $this->cargos($requisicion, $ocsVivas, $mapa);

            foreach ($cargos as $cargo) {
                $this->moverCargo($cargo, $mapa[$cargo->obra_rubro_id], $motivo, $userId);
            }

            $solicitudes = SolicitudPago::query()->whereIn('orden_compra_id', $ocsVivas->modelKeys())->pluck('id');
            $cerrados = ObraRubro::query()->with('presupuesto:id,estatus')->whereIn('id', array_values($mapa))->get()
                ->mapWithKeys(fn (ObraRubro $or): array => [$or->id => $or->estaCerrado()]);

            foreach ($mapa as $origen => $destino) {
                $detalleIds = $requisicion->detalles()->pluck('id');

                RequisicionDetalle::query()->whereIn('id', $detalleIds)->where('obra_rubro_id', $origen)->update(['obra_rubro_id' => $destino]);
                RequisicionSeleccion::query()->whereIn('requisicion_detalle_id', $detalleIds)->where('obra_rubro_id', $origen)->update(['obra_rubro_id' => $destino]);
                OrdenCompraDetalle::query()->whereIn('orden_compra_id', $ocsVivas->modelKeys())->where('obra_rubro_id', $origen)->update(['obra_rubro_id' => $destino]);
                SolicitudPagoDetalle::query()->whereIn('solicitud_id', $solicitudes)->where('obra_rubro_id', $origen)->update([
                    'obra_rubro_id' => $destino,
                    'sobre_obra_cerrada' => $cerrados[$destino] ?? false,
                ]);
            }

            $this->actualizarEncabezado($requisicion);

            activity('costos')
                ->performedOn($requisicion)
                ->causedBy($userId ? Usuario::find($userId) : null)
                ->withProperties([
                    'motivo' => $motivo,
                    'mapa' => $mapa,
                    'partidas' => $plan['partidas'],
                    'cargos' => $plan['cargos'],
                    'ocs_omitidas' => $plan['ocs_omitidas'],
                ])
                ->log('Centros de costos reasignados por comando');

            return ['partidas' => count($plan['partidas']), 'cargos' => $cargos->count()];
        });
    }

    /**
     * @param  array<int, int>  $mapa
     * @param  Collection<int, ObraRubro>  $rubros
     * @return list<string>
     */
    private function errores(Requisicion $requisicion, array $mapa, Collection $rubros): array
    {
        $errores = [];

        if ($requisicion->estatus === RequisicionEstatus::Cancelada) {
            $errores[] = "La requisición {$requisicion->folio} está cancelada.";
        }

        if ($mapa === []) {
            $errores[] = 'El mapa está vacío: indica al menos un --mapa=viejo:nuevo.';
        }

        $usados = $requisicion->detalles()->pluck('obra_rubro_id')->filter()->map(fn ($id): int => (int) $id)->unique();

        foreach ($mapa as $origen => $destino) {
            if (! $rubros->has($origen)) {
                $errores[] = "No existe el centro de costos {$origen}.";
            } elseif (! $usados->contains($origen)) {
                $errores[] = "La requisición no carga al centro de costos {$origen}.";
            }

            if (! $rubros->has($destino)) {
                $errores[] = "No existe el centro de costos {$destino}.";
            }

            if ($origen === $destino) {
                $errores[] = "El centro de costos {$origen} se mapea a sí mismo.";
            }

            // Un encadenado (A→B y B→C) movería dos veces lo que ya estaba en B.
            if ($origen !== $destino && array_key_exists($destino, $mapa)) {
                $errores[] = "El centro de costos {$destino} es destino y también origen: haz la reasignación en dos pasos.";
            }
        }

        $conRecepciones = Entrega::query()
            ->activa()
            ->whereIn('orden_compra_id', $this->ocsVivas($requisicion)->modelKeys())
            ->with('ordenCompra:id,folio')
            ->get()
            ->map(fn (Entrega $e): string => (string) $e->ordenCompra?->folio)
            ->unique()
            ->values();

        if ($conRecepciones->isNotEmpty()) {
            $errores[] = 'Hay material recibido en '.$conRecepciones->join(', ').': quedó asignado en almacén a la obra vieja. Cancela las recepciones primero.';
        }

        return $errores;
    }

    /**
     * @return Collection<int, OrdenCompra>
     */
    private function ocsVivas(Requisicion $requisicion): Collection
    {
        return $requisicion->ordenesGeneradas()
            ->where('estatus', '!=', OrdenCompraEstatus::Cancelada->value)
            ->get();
    }

    /**
     * Partidas que cambian de centro de costos, para el plan.
     *
     * @param  Collection<int, OrdenCompra>  $ocsVivas
     * @param  array<int, int>  $mapa
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function partidas(Requisicion $requisicion, Collection $ocsVivas, array $mapa): array
    {
        $origenes = array_keys($mapa);
        $partidas = [];

        foreach ($requisicion->detalles()->whereIn('obra_rubro_id', $origenes)->get() as $detalle) {
            $partidas[] = [$requisicion->folio, (string) $detalle->descripcion, (int) $detalle->obra_rubro_id];
        }

        foreach ($ocsVivas as $oc) {
            foreach ($oc->detalles()->whereIn('obra_rubro_id', $origenes)->get() as $detalle) {
                $partidas[] = [$oc->folio, (string) $detalle->descripcion, (int) $detalle->obra_rubro_id];
            }
        }

        $solicitudes = SolicitudPago::query()->whereIn('orden_compra_id', $ocsVivas->modelKeys())->get();

        foreach ($solicitudes as $solicitud) {
            foreach ($solicitud->detalles()->whereIn('obra_rubro_id', $origenes)->get() as $detalle) {
                $partidas[] = [(string) $solicitud->folio, (string) $detalle->concepto, (int) $detalle->obra_rubro_id];
            }
        }

        return $partidas;
    }

    /**
     * Cargos vivos (apartados y aplicados) de la requisición y de sus órdenes
     * vivas sobre los centros de costos que se mueven.
     *
     * @param  Collection<int, OrdenCompra>  $ocsVivas
     * @param  array<int, int>  $mapa
     * @return Collection<int, RubroAfectado>
     */
    private function cargos(Requisicion $requisicion, Collection $ocsVivas, array $mapa): Collection
    {
        return RubroAfectado::query()
            ->with('entrada')
            ->whereIn('estatus', array_map(fn (RubroAfectadoEstatus $e): string => $e->value, RubroAfectadoEstatus::activos()))
            ->whereIn('obra_rubro_id', array_keys($mapa))
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('entrada_type', Requisicion::class)->where('entrada_id', $requisicion->getKey()))
                ->orWhere(fn ($w) => $w->where('entrada_type', OrdenCompra::class)->whereIn('entrada_id', $ocsVivas->modelKeys())))
            ->orderBy('id')
            ->get();
    }

    /**
     * Revierte el cargo en su centro de costos y lo vuelve a aplicar en el
     * destino con el mismo monto en pesos. Se aplica en pesos y después se
     * sellan divisa, monto de origen y tipo de cambio del original: recalcular
     * desde la divisa podría mover centavos o re-cotizar un cargo que la
     * conciliación de tipo de cambio ya corrigió.
     */
    private function moverCargo(RubroAfectado $cargo, int $destinoId, string $motivo, ?string $userId): void
    {
        $original = $cargo->only(['estatus', 'monto', 'moneda', 'monto_origen', 'tipo_cambio', 'tipo_movimiento', 'descripcion', 'apartado_hasta']);

        if ($cargo->estatus === RubroAfectadoEstatus::Aplicado) {
            $this->ledger->registrarPorId($cargo->obra_rubro_id, -(float) $cargo->monto, $cargo->id, $motivo, $userId);
        } else {
            $origen = ObraRubro::whereKey($cargo->obra_rubro_id)->lockForUpdate()->firstOrFail();
            $origen->update(['apartado' => max(0.0, (float) $origen->apartado - (float) $cargo->monto)]);
        }

        $cargo->update([
            'estatus' => RubroAfectadoEstatus::Cancelado,
            'descripcion' => trim(($cargo->descripcion ? $cargo->descripcion.' · ' : '').$motivo),
        ]);

        $nuevo = $this->apartado->aplicarCargo(
            entrada: $cargo->entrada,
            obraRubroId: $destinoId,
            monto: (float) $original['monto'],
            estatus: $original['estatus'],
            descripcion: $original['descripcion'],
            userId: $userId,
            apartadoHasta: $original['apartado_hasta'] !== null ? Carbon::instance($original['apartado_hasta']) : null,
            allowSobregiro: true,
            moneda: 'mxn',
            tc: 1.0,
        );

        $nuevo->update([
            'moneda' => $original['moneda'],
            'monto_origen' => $original['monto_origen'],
            'tipo_cambio' => $original['tipo_cambio'],
            'tipo_movimiento' => $original['tipo_movimiento'],
        ]);
    }

    /**
     * El encabezado sigue a sus partidas: un solo presupuesto queda como el de
     * la requisición; varios la dejan sin presupuesto de encabezado, igual que
     * al capturarla multipresupuesto.
     */
    private function actualizarEncabezado(Requisicion $requisicion): void
    {
        $rubros = ObraRubro::query()
            ->with('presupuesto:id,estatus')
            ->whereIn('id', $requisicion->detalles()->pluck('obra_rubro_id')->filter())
            ->get();

        if ($rubros->isEmpty()) {
            return;
        }

        $presupuestos = $rubros->pluck('presupuesto_id')->unique();

        $requisicion->update([
            'presupuesto_id' => $presupuestos->count() === 1 ? $presupuestos->first() : null,
            'sin_centro_costos' => false,
            'sobre_obra_cerrada' => $rubros->contains(fn (ObraRubro $or): bool => $or->estaCerrado()),
        ]);
    }

    private function documento(RubroAfectado $cargo): string
    {
        return (string) ($cargo->entrada?->folio ?? class_basename($cargo->entrada_type).' #'.$cargo->entrada_id);
    }

    private function etiqueta(?ObraRubro $obraRubro): string
    {
        if ($obraRubro === null) {
            return '—';
        }

        return sprintf(
            '#%d %s · %s',
            $obraRubro->id,
            $obraRubro->presupuesto?->nombreMostrar() ?? 'sin presupuesto',
            trim(($obraRubro->rubro?->codigo ?? '').' '.($obraRubro->rubro?->descripcion ?? '')),
        );
    }
}
