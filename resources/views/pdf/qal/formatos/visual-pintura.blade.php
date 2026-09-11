{{-- Inspección visual de pintura (formato por asignar). Ver App\Services\Qal\Formatos\VisualPintura. --}}
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
                    <th rowspan="2">Marca</th>
                    <th rowspan="2"># de<br>consec.</th>
                    <th rowspan="2">Mód.</th>
                    <th rowspan="2">Área<br>(m²)</th>
                    <th rowspan="2">Inspector</th>
                    <th colspan="3">Criterios</th>
                    <th colspan="{{ count($datos['defectos']) }}">Defecto detectado</th>
                    <th rowspan="2">Esp. prom.<br>(mils)</th>
                    <th rowspan="2">Req.<br>(mils)</th>
                    <th rowspan="2">Insp.</th>
                    <th rowspan="2">Resultado</th>
                    <th rowspan="2" style="width: 120pt;">Observaciones</th>
                </tr>
                <tr>
                    <th>Espesor</th>
                    <th>Visual</th>
                    <th>Adher.</th>
                    @foreach ($datos['defectos'] as $defecto)
                        <th>{{ $defecto }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td>{{ $r['no'] }}</td>
                        <td>{{ $r['fecha'] }}</td>
                        <td class="izq nowrap"><b>{{ $r['marca'] }}</b></td>
                        <td>{{ $r['consecutivo'] }}</td>
                        <td>{{ $r['modulo'] }}</td>
                        <td>{{ $r['area'] !== null ? number_format($r['area'], 2) : '' }}</td>
                        <td class="persona">{{ $r['inspector'] }}</td>
                        @foreach ([...$r['criterios'], ...$r['defectos']] as $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda])
                        @endforeach
                        <td>{!! $r['promedio'] !== null ? '<b>'.e($r['promedio']).'</b>' : '<span class="nulo">s/m</span>' !!}</td>
                        <td>{{ $r['requerido'] }}</td>
                        <td class="{{ $r['inspeccion'] > 1 ? 'a-def' : '' }}">{{ $r['inspeccion'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                        <td class="txt">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="izq">TOTALES</td>
                    <td>{{ $t['area'] ? number_format($t['area'], 2) : '' }}</td>
                    <td colspan="{{ 3 + count($datos['defectos']) + 4 }}" class="izq">{{ $t['piezas'] }} pieza(s)</td>
                    <td>{{ $t['liberadas'] }} L · {{ $t['rechazadas'] }} R{{ $t['pendientes'] ? ' · '.$t['pendientes'].' P' : '' }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @if ($datos['aviso'])
            <div class="aviso">{{ $datos['aviso'] }}</div>
        @endif
    @endif
</x-pdf.formato-qal>
