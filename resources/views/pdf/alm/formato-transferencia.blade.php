{{--
    El traslado entre bodegas, que ocurre en dos tiempos: sale de una y llega a
    otra, y en medio el material va en el camión sin ser existencia de nadie.

    Por eso son cuatro firmas y no dos: además de quien autoriza, firma quien
    despacha y quien recibe, que casi nunca son la misma persona ni el mismo
    día. La hoja viaja con el material y vuelve firmada por el otro extremo:
    es el comprobante de que llegó, y de cuánto llegó.
--}}
<x-pdf.formato-alm
    titulo="TRANSFERENCIA"
    subtitulo="TRASLADO DE MATERIAL ENTRE ALMACENES"
    :folio="$transferencia->folio">

    @if ($transferencia->cancelada_at)
        <div class="cancelada">
            CANCELADA
            <div class="detalle">
                {{ $transferencia->cancelada_at?->format('d/m/Y H:i') }} — {{ $transferencia->motivo_cancelacion ?: 'Sin motivo asentado' }}.
                El material volvió al almacén de origen: esta hoja ya no ampara nada.
            </div>
        </div>
    @endif

    <table class="info-table">
        <tr>
            <td class="label">Sale de</td>
            <td><strong>{{ trim(($transferencia->origen?->clave ? $transferencia->origen->clave.' - ' : '').($transferencia->origen?->nombre ?? '')) ?: '-' }}</strong></td>
            <td class="label">Llega a</td>
            <td><strong>{{ trim(($transferencia->destino?->clave ? $transferencia->destino->clave.' - ' : '').($transferencia->destino?->nombre ?? '')) ?: '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Fecha de envío</td>
            <td>{{ $transferencia->fecha_envio?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Fecha de recepción</td>
            <td>{{ $transferencia->fecha_recepcion?->format('d/m/Y') ?? 'En tránsito' }}</td>
        </tr>
        <tr>
            <td class="label">Surte el pedido</td>
            <td>{{ $transferencia->pedido?->folio ?? 'Traslado directo, sin pedido' }}</td>
            <td class="label">Estatus</td>
            <td>{{ ucfirst(str_replace('_', ' ', $transferencia->estatus?->value ?? '-')) }}</td>
        </tr>
        @if ($transferencia->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $transferencia->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 16%;">Enviado</th>
                <th style="width: 16%;">Recibido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transferencia->detalles as $d)
            <tr>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->articulo?->descripcion ?? '-' }}
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $d->cantidad_enviada, 2) }} {{ $d->articulo?->unidad }}</td>
                {{-- En blanco mientras va en camino: el renglón se llena al
                     contar contra la hoja, que es de lo que se trata. --}}
                <td class="text-right">{{ $d->cantidad_recibida === null ? '' : number_format((float) $d->cantidad_recibida, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td class="text-right">{{ $transferencia->detalles->count() }} renglón(es)</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => [
        ['nombre' => $transferencia->autorizador?->name, 'rol' => 'Autorizó'],
        ['nombre' => $transferencia->enviador?->name, 'rol' => 'Despachó - Origen'],
        ['nombre' => $transferencia->receptor?->name, 'rol' => 'Recibió - Destino'],
    ]])

    <div class="aviso">
        Quien firma de recibido responde por el material a partir de ese momento. Las diferencias contra lo enviado deben
        anotarse en esta misma hoja antes de firmar: una vez confirmada, el almacén de destino carga la cantidad asentada.
    </div>
</x-pdf.formato-alm>
