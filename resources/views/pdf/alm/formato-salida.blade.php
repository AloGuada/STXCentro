{{--
    El vale: lo que de verdad salió del almacén y quién se lo llevó.

    Es el único de los formatos que ya existía, y ahora comparte el armazón con
    los otros tres. Sus dos firmas se quedan como estaban: aquí no hay una
    autorización que resolver, porque el material ya se entregó — lo que la hoja
    documenta es la entrega misma.
--}}
<x-pdf.formato-alm
    titulo="VALE DE SALIDA"
    subtitulo="ENTREGA DE MATERIAL DE ALMACÉN"
    :folio="$salida->folio ?? ('SAL-'.str_pad((string) $salida->id, 4, '0', STR_PAD_LEFT))">

    @php
        $total = 0.0;
        foreach ($salida->detalles as $d) {
            $total += $d->importe();
        }
    @endphp

    @if ($salida->estaCancelada())
        <div class="cancelada">
            CANCELADA
            <div class="detalle">
                {{ $salida->cancelada_at?->format('d/m/Y H:i') }} — {{ $salida->motivo_cancelacion ?: 'Sin motivo asentado' }}.
                El material volvió al kardex con un movimiento espejo: esta hoja ya no ampara nada.
            </div>
        </div>
    @endif

    <table class="info-table">
        <tr>
            <td class="label">Fecha</td>
            <td>{{ $salida->fecha?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($salida->almacen?->clave ? $salida->almacen->clave.' - ' : '').($salida->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Recibe</td>
            <td><strong>{{ $salida->recibe_nombre ?: '-' }}</strong></td>
            <td class="label">Módulo</td>
            <td>{{ $salida->grupoTrabajo?->descripcion ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $salida->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Surte el pedido</td>
            <td>{{ $salida->pedido?->folio ?? 'Salida directa, sin pedido' }}</td>
        </tr>
        <tr>
            <td class="label">Entregó</td>
            <td>{{ $salida->entregador?->name ?? '-' }}</td>
            <td class="label">Registrada</td>
            <td>{{ $salida->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        @if ($salida->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $salida->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 14%;">Cantidad</th>
                <th style="width: 15%;">Costo unitario</th>
                <th style="width: 15%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salida->detalles as $d)
            <tr>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->articulo?->descripcion ?? '-' }}
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $d->cantidad, 2) }} {{ $d->articulo?->unidad }}</td>
                <td class="text-right">{{ $d->costo_unitario === null ? '-' : '$'.number_format((float) $d->costo_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->importe(), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td class="text-right">{{ $salida->detalles->count() }} renglón(es)</td>
                <td></td>
                <td class="text-right">${{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => $firmas])

    <div class="aviso">
        Quien firma de recibido responde por el material amparado en este vale hasta su consumo o devolución.
        Cualquier diferencia debe reclamarse en el momento de la entrega: una vez firmado, el almacén considera surtida la cantidad asentada.
    </div>
</x-pdf.formato-alm>
