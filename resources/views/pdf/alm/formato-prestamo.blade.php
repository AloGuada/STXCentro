{{--
    El resguardo: quien se lleva los activos responde por ellos con su firma.

    No es un vale de salida —nada baja del almacén—, es la constancia de la
    custodia. Por eso las tres firmas: quien entrega, quien se lo lleva y quien
    autorizó que saliera.
--}}
<x-pdf.formato-alm
    titulo="RESGUARDO DE ACTIVOS"
    subtitulo="PRÉSTAMO DE HERRAMIENTA Y EQUIPO"
    :folio="$prestamo->folio">

    <table class="info-table">
        <tr>
            <td class="label">Salida</td>
            <td>{{ $prestamo->fecha_salida?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($prestamo->almacen?->clave ? $prestamo->almacen->clave.' - ' : '').($prestamo->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Responsable</td>
            <td><strong>{{ $prestamo->responsable?->name ?? '-' }}</strong></td>
            <td class="label">Destino</td>
            <td>{{ $prestamo->destino() }}</td>
        </tr>
        <tr>
            <td class="label">Debe volver</td>
            <td>{{ $prestamo->fecha_retorno_esperada?->format('d/m/Y') ?? 'Sin fecha (mientras dure la obra)' }}</td>
            <td class="label">Estado</td>
            <td>{{ $prestamo->estatus->etiqueta() }}</td>
        </tr>
        @if ($prestamo->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $prestamo->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 13%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 16%;">Serie</th>
                <th style="width: 10%;">Cantidad</th>
                <th style="width: 20%;">Condición al salir</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestamo->detalles as $d)
            <tr>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->articulo?->descripcion ?? '-' }}
                    @if ($d->activo && ($d->activo->marca || $d->activo->modelo))
                        <div class="obs">{{ trim(($d->activo->marca ?? '').' '.($d->activo->modelo ?? '')) }}</div>
                    @endif
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td>{{ $d->activo?->no_serie ?? '-' }}</td>
                <td class="text-right">{{ number_format((float) $d->cantidad, $d->esPorPieza() ? 0 : 2) }} {{ $d->articulo?->unidad }}</td>
                <td>{{ $d->condicion_salida ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-right">TOTAL</td>
                <td colspan="2">{{ $prestamo->detalles->count() }} renglón(es) · {{ number_format((float) $prestamo->detalles->sum('cantidad'), 0) }} unidad(es)</td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => $firmas])

    <div class="aviso">
        Lo aquí listado sigue siendo del almacén y queda bajo resguardo de quien firma como responsable, quien responde por su
        devolución en la condición en que salió. Este documento no descarga existencia.
    </div>
</x-pdf.formato-alm>
