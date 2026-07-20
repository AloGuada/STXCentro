<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\EntregaStoreRequest;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EntregaController extends Controller
{
    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    public function store(EntregaStoreRequest $request, OrdenCompra $ordenCompra): RedirectResponse
    {
        $partidasOrden = $ordenCompra->detalles()->pluck('id')->all();
        $detallesInput = $request->input('detalles', []);

        // 1. Validar que todas las partidas enviadas pertenezcan a esta OC
        foreach ($detallesInput as $i => $detalle) {
            if (! in_array((int) $detalle['orden_compra_detalle_id'], $partidasOrden, true)) {
                return back()->withErrors([
                    "detalles.{$i}.orden_compra_detalle_id" => 'La partida no pertenece a esta orden de compra.',
                ]);
            }
        }

        // 2. Validar que cantidad_recibida <= cantidad_ordenada - ya_recibida (por partida)
        $yaRecibidoPorPartida = EntregaDetalle::query()
            ->whereIn('orden_compra_detalle_id', $partidasOrden)
            ->selectRaw('orden_compra_detalle_id, SUM(cantidad_recibida) as total')
            ->groupBy('orden_compra_detalle_id')
            ->pluck('total', 'orden_compra_detalle_id')
            ->map(fn ($v) => (float) $v);

        $ordenCompraDetalles = OrdenCompraDetalle::whereIn('id', $partidasOrden)
            ->get()
            ->keyBy('id');

        $acumuladoEnviado = [];
        foreach ($detallesInput as $i => $detalle) {
            $ocdId = (int) $detalle['orden_compra_detalle_id'];
            $ocd = $ordenCompraDetalles->get($ocdId);
            $cantidadRecibida = (float) $detalle['cantidad_recibida'];

            $acumuladoEnviado[$ocdId] = ($acumuladoEnviado[$ocdId] ?? 0) + $cantidadRecibida;

            $saldoPendiente = (float) $ocd->cantidad - (float) ($yaRecibidoPorPartida[$ocdId] ?? 0);
            $totalEnviado = $acumuladoEnviado[$ocdId];

            if ($totalEnviado > $saldoPendiente + config('costos.epsilon_cantidad')) {
                return back()->withErrors([
                    "detalles.{$i}.cantidad_recibida" => sprintf(
                        'Excede el saldo pendiente (%.2f %s) de la partida "%s".',
                        $saldoPendiente,
                        $ocd->unidad,
                        $ocd->descripcion,
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($request, $ordenCompra, $detallesInput, $ordenCompraDetalles) {
            $entrega = $ordenCompra->entregas()->create([
                'recibido_por' => $request->user()->id,
                'fecha_entrega' => $request->input('fecha_entrega'),
                'tipo' => $request->input('tipo'),
                'observaciones' => $request->input('observaciones'),
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $entrega->media()->create([
                    'descripcion' => DocumentoTipo::EvidenciaRecepcion->value,
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/entregas', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            foreach ($detallesInput as $detalle) {
                $ocd = $ordenCompraDetalles->get((int) $detalle['orden_compra_detalle_id']);
                $precioRecibido = isset($detalle['precio_unitario']) && $detalle['precio_unitario'] !== ''
                    ? (float) $detalle['precio_unitario']
                    : null;

                $entrega->detalles()->create([
                    'orden_compra_detalle_id' => $detalle['orden_compra_detalle_id'],
                    'cantidad_recibida' => $detalle['cantidad_recibida'],
                    'precio_unitario' => $precioRecibido,
                    'observaciones' => $detalle['observaciones'] ?? null,
                ]);

                $this->ajustarPresupuestoPorDiferenciaPrecio(
                    $ordenCompra,
                    $ocd,
                    $precioRecibido,
                    (float) $detalle['cantidad_recibida'],
                    $request->user()->id,
                );
            }
        });

        return back()->with('success', 'Entrega registrada correctamente.');
    }

    /**
     * Al recibir a un precio distinto del de la OC (ej. acero que se iguala a la
     * factura), el acumulado del rubro ya trae el cargo al precio de la OC. Se
     * registra solo la diferencia: delta = (PU recibido − PU OC) × cantidad. El
     * cargo se liga a la OC (RubroAfectado) para que se revierta si se cancela.
     */
    private function ajustarPresupuestoPorDiferenciaPrecio(
        OrdenCompra $ordenCompra,
        ?OrdenCompraDetalle $ocd,
        ?float $precioRecibido,
        float $cantidadRecibida,
        string $userId,
    ): void {
        if ($precioRecibido === null || $ocd === null || $ocd->obra_rubro_id === null) {
            return;
        }

        $delta = ($precioRecibido - (float) $ocd->precio_unitario) * $cantidadRecibida;

        if (abs($delta) < 0.005) {
            return;
        }

        $this->apartado->aplicarCargo(
            entrada: $ordenCompra,
            obraRubroId: (int) $ocd->obra_rubro_id,
            monto: $delta,
            estatus: RubroAfectadoEstatus::Aplicado,
            descripcion: "Ajuste PU recepción · {$ocd->descripcion}",
            userId: $userId,
            allowSobregiro: true,
        );
    }
}
