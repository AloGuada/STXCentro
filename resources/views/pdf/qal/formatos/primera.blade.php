{{-- Inspección de 1ª transformación (formato por asignar). Ver App\Services\Qal\Formatos\PrimeraTransformacion. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        @php($t = $datos['totales'])
        <table class="grid">
            <thead>
                <tr>
                    <th rowspan="2">No.</th>
                    <th rowspan="2">Fecha</th>
                    <th rowspan="2" style="width: 64pt;">Marca</th>
                    <th rowspan="2">Tipo</th>
                    <th rowspan="2">Cant.</th>
                    <th rowspan="2">Equipo</th>
                    <th rowspan="2">Operador</th>
                    <th rowspan="2">Inspector</th>
                    <th colspan="{{ count($datos['criterios']) }}" class="g0">Criterios de inspección</th>
                    <th colspan="4" class="g0">Muestreo del lote</th>
                    <th rowspan="2" class="g0">Insp.</th>
                    <th rowspan="2">Resultado</th>
                    <th rowspan="2" style="width: 90pt;">Observaciones</th>
                </tr>
                <tr>
                    @foreach ($datos['criterios'] as $i => $criterio)
                        <th class="vert {{ $i === 0 ? 'g0' : '' }}">@include('pdf.qal.partials.rotulo', ['texto' => $criterio, 'alto' => 64])</th>
                    @endforeach
                    <th class="g0">Lote</th>
                    <th>Muestra</th>
                    <th>Rech.</th>
                    <th>Veredicto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td>{{ $r['no'] }}</td>
                        <td>{{ $r['fecha'] }}</td>
                        <td class="izq nowrap"><b>{{ $r['marca'] }}</b></td>
                        <td>{{ $r['tipo'] }}</td>
                        <td>{{ $r['cantidad'] }}</td>
                        <td class="persona">{{ $r['equipo'] }}</td>
                        <td class="persona">{{ $r['operador'] }}</td>
                        <td class="persona">{{ $r['inspector'] }}</td>
                        @foreach ($r['criterios'] as $i => $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda, 'clase' => $i === 0 ? 'g0' : ''])
                        @endforeach
                        @if ($r['muestreo'])
                            <td class="g0">{{ $r['muestreo']['lote'] }}</td>
                            <td>{{ $r['muestreo']['muestra'] }}</td>
                            <td class="{{ $r['muestreo']['rechazadas'] ? 'a-def' : '' }}">{{ $r['muestreo']['rechazadas'] }}</td>
                            @include('pdf.qal.partials.celda', ['c' => $r['muestreo']['veredicto']])
                        @else
                            <td class="g0 nulo">—</td>
                            <td class="nulo">—</td>
                            <td class="nulo">—</td>
                            <td class="nulo">—</td>
                        @endif
                        <td class="g0 {{ $r['inspeccion'] > 1 ? 'a-def' : '' }}">{{ $r['inspeccion'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                        <td class="txt">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="izq">TOTALES</td>
                    <td>{{ $t['piezas'] }}</td>
                    <td colspan="{{ 3 + count($datos['criterios']) + 5 }}" class="izq">{{ $t['registros'] }} registro(s)</td>
                    <td>{{ $t['liberadas'] }} L · {{ $t['rechazadas'] }} R{{ $t['pendientes'] ? ' · '.$t['pendientes'].' P' : '' }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</x-pdf.formato-qal>
