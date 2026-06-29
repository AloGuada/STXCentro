<?php

namespace App\Services\Costos;

use App\Enums\Costos\ModoPago;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Proveedor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrdenCompraGenerator
{
    public function __construct(
        private readonly ApartadoPresupuestal $apartado,
        private readonly SolicitudPagoDesdeOrdenCompra $solicitudDesdeOc,
    ) {}

    /**
     * Genera las OCs de una requisición liberada: una por cada grupo
     * (proveedor, numero_oc). Libera los apartados temporales y, por cada OC,
     * crea sus detalles y aplica el impacto presupuestal permanente. El neto
     * sobre el acumulado es ~cero (apartado out, aplicado in por mismo monto).
     *
     * @param  Collection<string, Collection<int, RequisicionSeleccion>>  $grupos
     * @param  Collection<string, array<string, mixed>>  $ocsPayload
     */
    public function generar(Requisicion $requisicion, Collection $grupos, Collection $ocsPayload, string $userId): void
    {
        DB::transaction(function () use ($requisicion, $grupos, $ocsPayload, $userId) {
            $this->apartado->cancelarApartadosDe($requisicion, 'liberada a OC');

            foreach ($grupos as $key => $selecciones) {
                $this->crearOrden($requisicion, $selecciones, $ocsPayload[$key], $userId);
            }

            $requisicion->transitionTo(RequisicionEstatus::Liberada);
        });
    }

    /**
     * @param  Collection<int, RequisicionSeleccion>  $selecciones
     * @param  array<string, mixed>  $payload
     */
    private function crearOrden(Requisicion $requisicion, Collection $selecciones, array $payload, string $userId): void
    {
        $proveedor = Proveedor::find((int) $payload['proveedor_id']);
        $modoPago = (string) $payload['modo_pago'];

        // La moneda de la OC proviene de las cotizaciones seleccionadas; deben
        // ser todas la misma (validado en RequisicionController::liberar).
        $monedas = $selecciones
            ->map(fn (RequisicionSeleccion $s) => $s->cotizacionPrecio?->moneda ?? 'mxn')
            ->unique()
            ->values();

        if ($monedas->count() > 1) {
            throw new \RuntimeException('Una orden de compra no puede mezclar monedas.');
        }

        $moneda = (string) ($monedas->first() ?? 'mxn');

        $subtotalLineas = $selecciones->reduce(function ($acc, RequisicionSeleccion $s) {
            $precio = (float) ($s->cotizacionPrecio?->precio_unitario ?? 0);

            return $acc + $precio * (float) $s->cantidad;
        }, 0.0);

        $total = $subtotalLineas + $subtotalLineas * (float) config('costos.iva_rate');

        $diasCredito = ($modoPago === 'credito' && $proveedor?->maneja_credito)
            ? (int) ($proveedor->dias_credito_default ?? 0)
            : 0;

        $oc = OrdenCompra::create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => (int) $payload['proveedor_id'],
            'departamento_id' => $requisicion->departamento_id,
            'creado_por' => $userId,
            'moneda' => $moneda,
            'tipo_pago' => $modoPago,
            'dias_credito' => $diasCredito,
            'total' => round($total, 2),
            'fecha_entrega_esperada' => $payload['fecha_entrega'] ?? now()->addDays(7)->format('Y-m-d'),
            'notas' => $payload['notas'] ?? null,
            'estatus' => OrdenCompraEstatus::PendienteEntrega->value,
        ]);

        foreach ($selecciones as $sel) {
            /** @var RequisicionSeleccion $sel */
            $detalle = $sel->detalle ?? RequisicionDetalle::find($sel->requisicion_detalle_id);
            $precioUnit = (float) ($sel->cotizacionPrecio?->precio_unitario ?? 0);
            $cantidad = (float) $sel->cantidad;

            $ocDetalle = OrdenCompraDetalle::create([
                'orden_compra_id' => $oc->id,
                'requisicion_detalle_id' => $sel->requisicion_detalle_id,
                'producto_id' => $detalle->producto_id,
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'uso_cfdi_id' => $detalle->uso_cfdi_id,
                'tipo_fiscal' => $detalle->tipo_fiscal,
                'descripcion' => $detalle->descripcion,
                'codigo_producto' => $sel->cotizacionPrecio?->codigo_producto ?? $detalle->codigo_producto,
                'unidad' => $detalle->unidad,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnit,
                'subtotal' => round($precioUnit * $cantidad, 2),
            ]);

            $sel->update([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'orden_compra_detalle_id' => $ocDetalle->id,
            ]);
        }

        $oc->load('detalles');
        $oc->aplicarImpactoPresupuestal($userId);

        // Las OCs de contado se pagan por adelantado vía solicitud(es) de pago
        // (el proveedor no usa el portal). Si hay parcialidades definidas, se
        // genera una solicitud por cada hito (% del total); si no, una sola por
        // el total. El crédito sigue el flujo normal de factura → pago.
        if ($oc->tipo_pago === ModoPago::Contado) {
            $parcialidades = array_values(array_filter((array) ($payload['pagos'] ?? [])));
            $metodoPago = (string) ($payload['metodo_pago'] ?? 'transferencia');
            $fechaPago = $payload['fecha_pago'] ?? null;

            if (count($parcialidades) > 0) {
                $this->solicitudDesdeOc->crearParcialidades($oc, $parcialidades, $userId, $metodoPago, $fechaPago);
            } else {
                $this->solicitudDesdeOc->crear($oc, $userId, $metodoPago, $fechaPago);
            }
        }
    }
}
