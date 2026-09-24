{{--
    El reporte de un conteo ya cerrado: lo que la hoja en blanco no podía
    decir. Qué decía el sistema, qué se encontró, la diferencia y cuánto vale.

    Las cantidades son las del ajuste —el saldo contra el que se midió al
    cerrar— y el valor es el que ese ajuste movió en el kardex. Los renglones
    exactos también van: el reporte dice qué se revisó, no sólo qué falló.
--}}
<x-pdf.formato-alm
    titulo="INVENTARIO CÍCLICO"
    subtitulo="REPORTE DE CONTEO"
    :folio="$conteo->folio">

    <table class="info-table">
        <tr>
            <td class="label">Fecha programada</td>
            <td>{{ $conteo->fecha_programada?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($conteo->almacen?->clave ? $conteo->almacen->clave.' - ' : '').($conteo->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de cierre</td>
            <td>{{ $conteo->fecha_cierre?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Responsable</td>
            <td>{{ $conteo->responsable?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Ajuste</td>
            <td>{{ $conteo->ajuste?->folio ?? '-' }}</td>
            <td class="label">Origen</td>
            <td>{{ $conteo->origen?->etiqueta() ?? '-' }}</td>
        </tr>
        @if ($conteo->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $conteo->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 11%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 6%;">Unidad</th>
                <th style="width: 14%;">Ubicación</th>
                <th style="width: 9%;">Sistema</th>
                <th style="width: 9%;">Contado</th>
                <th style="width: 9%;">Diferencia</th>
                <th style="width: 10%;">Valor dif.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($renglones as $r)
            <tr>
                <td class="text-right">{{ $r['orden'] }}</td>
                <td>{{ $r['codigo'] ?? '-' }}</td>
                <td>
                    {{ $r['descripcion'] ?? '-' }}
                    @if ($r['observaciones'])
                        <div class="obs">{{ $r['observaciones'] }}</div>
                    @endif
                </td>
                <td>{{ $r['unidad'] ?? '' }}</td>
                <td>{{ $r['ubicacion'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['sistema'], 2) }}</td>
                <td class="text-right">{{ number_format($r['contado'], 2) }}</td>
                <td class="text-right">{{ $r['diferencia'] > 0 ? '+' : '' }}{{ number_format($r['diferencia'], 2) }}</td>
                <td class="text-right">
                    @if (abs($r['valor']) > 0.005)
                        {{ $r['valor'] < 0 ? '-' : '' }}${{ number_format(abs($r['valor']), 2) }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                <td colspan="3" class="text-right">
                    {{ $resumen['con_diferencia'] }} con diferencia de {{ $resumen['renglones'] }}
                </td>
                <td class="text-right">{{ $resumen['valor_neto'] < 0 ? '-' : '' }}${{ number_format(abs($resumen['valor_neto']), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => $firmas])

    <div class="aviso">
        Las diferencias ya se aplicaron al kardex con el ajuste {{ $conteo->ajuste?->folio }}. Se valúan al costo promedio del
        artículo en el almacén; un artículo que nunca tuvo costo se valúa en cero.
    </div>
</x-pdf.formato-alm>
