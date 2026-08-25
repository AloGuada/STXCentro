<?php

namespace App\Services\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Services\Alm\RegistradorEntradaAlmacen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una recepción contra orden de compra.
 *
 * La captura vive en Almacén —es el almacenista quien recibe— pero las reglas
 * son de Costos y por eso viven aquí: el tope contra lo pedido, el ajuste de
 * presupuesto cuando el proveedor surtió a otro precio y el avance de la
 * factura. Almacén aporta lo suyo: a qué almacén entra, que es lo que mueve el
 * kardex.
 *
 * Los rechazos salen como ValidationException para que la pantalla los pinte
 * en el renglón que los provocó.
 */
class RegistradorRecepcion
{
    public function __construct(
        private readonly ApartadoPresupuestal $apartado,
        private readonly RegistradorEntradaAlmacen $almacen,
    ) {}

    /**
     * @param  array{
     *     fecha_entrega: string,
     *     tipo: string,
     *     factura_id?: int|string|null,
     *     completa_factura?: bool,
     *     observaciones?: string|null,
     *     detalles: list<array{orden_compra_detalle_id: int|string, cantidad_recibida: float|string, precio_unitario?: float|string|null, observaciones?: string|null}>
     * }  $datos
     *
     * @throws ValidationException
     */
    public function registrar(
        OrdenCompra $ordenCompra,
        Almacen $almacen,
        array $datos,
        string $userId,
        ?UploadedFile $evidencia = null,
    ): Entrega {
        $factura = $this->facturaDeLaOrden($ordenCompra, $datos['factura_id'] ?? null);
        $partidas = $this->partidasValidadas($ordenCompra, $datos['detalles']);

        $this->validarSaldos($datos['detalles'], $partidas);

        return DB::transaction(function () use ($ordenCompra, $almacen, $datos, $userId, $evidencia, $factura, $partidas): Entrega {
            $completaFactura = $factura !== null && ($datos['completa_factura'] ?? false);

            $entrega = $ordenCompra->entregas()->create([
                'almacen_id' => $almacen->id,
                'recibido_por' => $userId,
                'fecha_entrega' => $datos['fecha_entrega'],
                'factura_id' => $factura?->id,
                'tipo' => $datos['tipo'],
                'observaciones' => $datos['observaciones'] ?? null,
                'completa_factura' => $completaFactura,
            ]);

            if ($evidencia !== null) {
                $entrega->media()->create([
                    'descripcion' => DocumentoTipo::EvidenciaRecepcion->value,
                    'nombre_original' => $evidencia->getClientOriginalName(),
                    'path' => $evidencia->store('costos/entregas', 'public'),
                    'mime' => $evidencia->getMimeType(),
                    'size' => $evidencia->getSize(),
                ]);
            }

            foreach ($datos['detalles'] as $renglon) {
                $partida = $partidas->get((int) $renglon['orden_compra_detalle_id']);
                $precioRecibido = isset($renglon['precio_unitario']) && $renglon['precio_unitario'] !== ''
                    ? (float) $renglon['precio_unitario']
                    : null;

                $entrega->detalles()->create([
                    'orden_compra_detalle_id' => $partida->id,
                    // Se sella el artículo de la partida: si alguien la
                    // re-apunta después, el movimiento del kardex ya no debe
                    // cambiar de artículo.
                    'producto_id' => $partida->producto_id,
                    'descripcion' => $partida->descripcion,
                    'unidad' => $partida->unidad,
                    'cantidad_recibida' => $renglon['cantidad_recibida'],
                    'precio_unitario' => $precioRecibido,
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);

                $this->ajustarPresupuestoPorDiferenciaPrecio(
                    $ordenCompra,
                    $partida,
                    $precioRecibido,
                    (float) $renglon['cantidad_recibida'],
                    $userId,
                );
            }

            // Si esta recepción completa la factura, marcarla como entregada e
            // intentar avanzarla a aprobación (requiere además el comprobante).
            if ($completaFactura) {
                $factura->update(['completamente_entregada' => true]);
                $factura->intentarPasarAAprobacion();
            }

            // Y hasta el final el kardex, con los renglones ya escritos.
            $this->almacen->aplicar($entrega->load('detalles'), $userId);

            return $entrega;
        });
    }

    /**
     * @throws ValidationException
     */
    private function facturaDeLaOrden(OrdenCompra $ordenCompra, int|string|null $facturaId): ?Factura
    {
        if (! $facturaId) {
            return null;
        }

        $factura = $ordenCompra->facturas()->find($facturaId);

        if ($factura === null) {
            throw ValidationException::withMessages([
                'factura_id' => 'La factura no pertenece a esta orden de compra.',
            ]);
        }

        return $factura;
    }

    /**
     * Las partidas de la orden indexadas por id, verificando de paso que todo
     * lo capturado sea de ESTA orden.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return \Illuminate\Support\Collection<int, OrdenCompraDetalle>
     *
     * @throws ValidationException
     */
    private function partidasValidadas(OrdenCompra $ordenCompra, array $detalles): \Illuminate\Support\Collection
    {
        $partidas = $ordenCompra->detalles()->get()->keyBy('id');

        foreach ($detalles as $i => $renglon) {
            if (! $partidas->has((int) $renglon['orden_compra_detalle_id'])) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.orden_compra_detalle_id" => 'La partida no pertenece a esta orden de compra.',
                ]);
            }
        }

        return $partidas;
    }

    /**
     * Nadie puede recibir más de lo que falta: lo pedido menos lo ya recibido
     * en recepciones vigentes, contando lo que trae esta misma captura.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @param  \Illuminate\Support\Collection<int, OrdenCompraDetalle>  $partidas
     *
     * @throws ValidationException
     */
    private function validarSaldos(array $detalles, \Illuminate\Support\Collection $partidas): void
    {
        $yaRecibido = EntregaDetalle::query()
            ->whereIn('orden_compra_detalle_id', $partidas->keys())
            ->whereHas('entrega', fn ($q) => $q->activa())
            ->selectRaw('orden_compra_detalle_id, SUM(cantidad_recibida) as total')
            ->groupBy('orden_compra_detalle_id')
            ->pluck('total', 'orden_compra_detalle_id')
            ->map(fn ($v): float => (float) $v);

        $acumulado = [];

        foreach ($detalles as $i => $renglon) {
            $partidaId = (int) $renglon['orden_compra_detalle_id'];
            $partida = $partidas->get($partidaId);

            $acumulado[$partidaId] = ($acumulado[$partidaId] ?? 0) + (float) $renglon['cantidad_recibida'];
            $saldo = (float) $partida->cantidad - (float) ($yaRecibido[$partidaId] ?? 0);

            if ($acumulado[$partidaId] > $saldo + config('costos.epsilon_cantidad')) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.cantidad_recibida" => sprintf(
                        'Excede el saldo pendiente (%.2f %s) de la partida "%s".',
                        $saldo,
                        $partida->unidad,
                        $partida->descripcion,
                    ),
                ]);
            }
        }
    }

    /**
     * Cuando el proveedor surtió a un precio distinto al de la orden, el
     * presupuesto se corrige por la diferencia: la orden ya había apartado al
     * precio pactado.
     */
    private function ajustarPresupuestoPorDiferenciaPrecio(
        OrdenCompra $ordenCompra,
        OrdenCompraDetalle $partida,
        ?float $precioRecibido,
        float $cantidadRecibida,
        string $userId,
    ): void {
        if ($precioRecibido === null || $partida->obra_rubro_id === null) {
            return;
        }

        $delta = ($precioRecibido - (float) $partida->precio_unitario) * $cantidadRecibida;

        if (abs($delta) < 0.005) {
            return;
        }

        $this->apartado->aplicarCargo(
            entrada: $ordenCompra,
            obraRubroId: (int) $partida->obra_rubro_id,
            monto: $delta,
            estatus: RubroAfectadoEstatus::Aplicado,
            descripcion: "Ajuste PU recepción · {$partida->descripcion}",
            userId: $userId,
            allowSobregiro: true,
            moneda: $ordenCompra->moneda ?? 'mxn',
        );
    }
}
