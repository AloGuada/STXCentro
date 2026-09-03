{{--
    La requisición interna: lo que un área le pide al almacén.

    Lleva tres firmas porque son tres momentos distintos y tres
    responsabilidades: quien lo necesita, quien dice que sí, y quien lo surte.
    La de en medio es la que sustituye al flujo de aprobación que no existe —el
    pedido nace aprobado en el sistema y la autorización se resuelve en papel—,
    y por eso su renglón va en blanco: nadie la ha firmado todavía.
--}}
<x-pdf.formato-alm
    titulo="PEDIDO DE ALMACÉN"
    subtitulo="SOLICITUD DE MATERIAL"
    :folio="$pedido->folio">

    <table class="info-table">
        <tr>
            <td class="label">Fecha</td>
            <td>{{ $pedido->fecha?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($pedido->almacen?->clave ? $pedido->almacen->clave.' - ' : '').($pedido->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Solicita</td>
            <td><strong>{{ $pedido->solicitante?->name ?? '-' }}</strong></td>
            <td class="label">Recibe</td>
            <td>{{ $pedido->recibe_nombre ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $pedido->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Obra</td>
            <td>{{ $pedido->obra ? trim(($pedido->obra->no ? $pedido->obra->no.' - ' : '').$pedido->obra->descripcion) : 'Planta' }}</td>
        </tr>
        <tr>
            <td class="label">Se necesita para</td>
            <td>{{ $pedido->fecha_requerida?->format('d/m/Y') ?? 'Sin fecha comprometida' }}</td>
            <td class="label">Módulo</td>
            <td>{{ $pedido->grupoTrabajo?->descripcion ?? '-' }}</td>
        </tr>
        @if ($pedido->motivo)
        <tr>
            <td class="label">Motivo</td>
            <td colspan="3">{{ $pedido->motivo }}</td>
        </tr>
        @endif
        @if ($pedido->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $pedido->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 16%;">Solicitado</th>
                <th style="width: 16%;">Surtido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido->detalles as $d)
            <tr>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->articulo?->descripcion ?? '-' }}
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $d->cantidad_solicitada, 2) }} {{ $d->articulo?->unidad }}</td>
                {{-- Lo surtido va impreso aunque sea cero: la hoja sirve para
                     reclamar, y para eso tiene que decir qué falta. --}}
                <td class="text-right">{{ number_format((float) $d->cantidad_surtida, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td class="text-right">{{ $pedido->detalles->count() }} renglón(es)</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => [
        ['nombre' => $pedido->solicitante?->name, 'rol' => 'Solicitó'],
        ['nombre' => $pedido->aprobador?->name, 'rol' => 'Autorizó'],
        ['nombre' => null, 'rol' => 'Surtió - Almacén'],
    ]])

    <div class="aviso">
        Este pedido no ampara la entrega del material: lo que sale del almacén se documenta con su vale de salida.
        Mientras un renglón no aparezca surtido, sigue pendiente.
    </div>
</x-pdf.formato-alm>
