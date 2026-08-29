<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Producto;

/**
 * Aplica al kardex una recepción de `costos_entregas`.
 *
 * Vive como servicio invocado **explícitamente** desde los controladores y no
 * como observer del modelo. Un observer se dispararía en las factories y
 * seeders de la docena de tests de Costos que crean entregas sin almacén ni
 * existencias, el de la cabecera correría antes de que existan renglones, y la
 * cancelación necesita invocarlo en modo reverso — un `if` dentro de un hook de
 * `updated` sería frágil justo donde no puede fallar.
 *
 * Todos los métodos asumen que el llamador ya abrió su transacción: el
 * documento y su efecto en el kardex tienen que confirmarse juntos.
 */
class RegistradorEntradaAlmacen
{
    public function __construct(private readonly AlmacenLedger $ledger) {}

    /**
     * Carga al kardex los renglones de una recepción ya guardada.
     *
     * No-op sin `almacen_id`: Costos puede seguir capturando recepciones que no
     * pasan por un almacén, y esas no mueven existencia.
     *
     * Idempotente por documento: si ya dejó asientos, no vuelve a cargar.
     *
     * @return int cuántos renglones movieron el saldo
     */
    public function aplicar(Entrega $entrega, ?string $userId = null): int
    {
        if ($entrega->almacen_id === null || $this->yaAplicada($entrega)) {
            return 0;
        }

        $aplicados = 0;

        // La partida de la orden y su centro de costos, de una vez: de ahí salen
        // el artículo y la obra de cada renglón, y sin esto serían dos consultas
        // por renglón recibido.
        $entrega->loadMissing('detalles.ordenCompraDetalle.obraRubro');

        foreach ($entrega->detalles as $detalle) {
            $productoId = $this->productoDe($detalle);

            // Un renglón sin artículo, un servicio, o lo que Compras tecleó sin
            // código: se recibe —es lo que destraba la factura— pero no hay
            // existencia que mover. Un solo guard los cubre a los tres.
            if ($productoId === null || ! $this->llevaKardex($productoId)) {
                continue;
            }

            $this->ledger->registrarPorProducto(
                almacenId: (int) $entrega->almacen_id,
                productoId: $productoId,
                tipo: MovimientoTipo::Entrada,
                cantidad: (float) $detalle->cantidad_recibida,
                costoUnitario: $this->costoDe($detalle),
                documento: $entrega,
                referencia: $entrega->folio,
                observaciones: $detalle->observaciones,
                userId: $userId,
                obraId: $this->obraDe($detalle),
            );

            $aplicados++;
        }

        return $aplicados;
    }

    /**
     * Devuelve al proveedor lo que esta recepción había cargado.
     *
     * Movimiento espejo, no borrado: el folio y los dos asientos quedan. Si el
     * material ya se consumió, el saldo puede quedar en negativo — y eso es
     * información correcta, no un error: se gastó algo que ahora se dice no
     * haber recibido. Bloquear la cancelación convertiría un error de captura en
     * un callejón sin salida.
     *
     * @return int cuántos renglones se revirtieron
     */
    public function revertir(Entrega $entrega, string $motivo, ?string $userId = null): int
    {
        // Guard de documento completo, no de renglón: sin él, la segunda
        // llamada volvería a encontrar los asientos originales y devolvería el
        // material dos veces.
        if ($entrega->almacen_id === null || $this->yaRevertida($entrega)) {
            return 0;
        }

        $revertidos = 0;

        foreach ($this->movimientosDe($entrega)->where('es_reverso', false)->get() as $movimiento) {
            $this->ledger->registrarPorProducto(
                almacenId: (int) $movimiento->almacen_id,
                productoId: (int) $movimiento->producto_id,
                tipo: MovimientoTipo::Entrada,
                cantidad: -(float) $movimiento->cantidad,
                costoUnitario: $movimiento->costo_unitario === null ? null : (float) $movimiento->costo_unitario,
                documento: $entrega,
                referencia: $entrega->folio,
                observaciones: "Cancelación de recepción · {$motivo}",
                userId: $userId,
                permitirNegativo: true,
                esReverso: true,
                obraId: $movimiento->obra_id === null ? null : (int) $movimiento->obra_id,
            );

            $revertidos++;
        }

        return $revertidos;
    }

    /**
     * El artículo del renglón: el suyo si lo trae, y si no el de la partida de
     * la orden. Las recepciones viejas sólo tienen lo segundo.
     */
    private function productoDe(EntregaDetalle $detalle): ?int
    {
        if ($detalle->producto_id !== null) {
            return (int) $detalle->producto_id;
        }

        $partida = $detalle->ordenCompraDetalle;

        return $partida?->producto_id === null ? null : (int) $partida->producto_id;
    }

    /**
     * De quién es lo que entra.
     *
     * La compra ya sabe contra qué presupuesto se hizo: el renglón apunta a la
     * partida de la orden, la partida a su centro de costos y ése a su obra. Por
     * eso la asignación **no se captura** —sería volver a teclear algo que ya
     * está aprobado y firmado— y por eso una entrada sin orden (recepción libre,
     * carga inicial) nace sin dueño: no hay presupuesto de dónde deducirlo.
     */
    private function obraDe(EntregaDetalle $detalle): ?int
    {
        $obraId = $detalle->ordenCompraDetalle?->obraRubro?->obra_id;

        return $obraId === null ? null : (int) $obraId;
    }

    /**
     * Lo que costó de verdad: el precio capturado en la recepción, o el de la
     * orden si no se corrigió. `precio_unitario_efectivo` ya resuelve esa
     * herencia, y devuelve 0 cuando no hay ninguno — que para el ledger es «sin
     * costo», no «gratis».
     */
    private function costoDe(EntregaDetalle $detalle): ?float
    {
        $costo = (float) $detalle->precio_unitario_efectivo;

        return $costo > 0 ? $costo : null;
    }

    private function yaAplicada(Entrega $entrega): bool
    {
        return $this->movimientosDe($entrega)->exists();
    }

    private function yaRevertida(Entrega $entrega): bool
    {
        return $this->movimientosDe($entrega)->where('es_reverso', true)->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Movimiento>
     */
    private function movimientosDe(Entrega $entrega)
    {
        return Movimiento::query()
            ->where('documento_type', $entrega->getMorphClass())
            ->where('documento_id', $entrega->getKey());
    }

    private function llevaKardex(int $productoId): bool
    {
        return Producto::query()
            ->whereKey($productoId)
            ->where('controla_inventario', true)
            ->exists();
    }
}
