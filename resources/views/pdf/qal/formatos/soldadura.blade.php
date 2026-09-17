{{-- F-STX-CA-10 · Registro de inspección visual de soldadura. Ver App\Services\Qal\Formatos\Soldadura. --}}
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
                    <th rowspan="2">Marca / pieza</th>
                    <th rowspan="2"># de<br>consec.</th>
                    <th rowspan="2">Kg</th>
                    <th rowspan="2">Línea</th>
                    <th rowspan="2">Mód.</th>
                    <th rowspan="2">Soldador</th>
                    <th rowspan="2">Inspector</th>
                    <th colspan="2">Control de proceso</th>
                    <th rowspan="2">Elem.</th>
                    <th rowspan="2">Defectos</th>
                    <th rowspan="2">Tipo de defecto</th>
                    <th colspan="2">Acabado</th>
                    <th rowspan="2">Insp.</th>
                    <th rowspan="2">Resultado</th>
                    <th rowspan="2">Observaciones</th>
                </tr>
                <tr>
                    <th>Precal.</th>
                    <th>Limp. entre<br>pasadas</th>
                    <th>Limpieza<br>mecánica</th>
                    <th>Etiqueta</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td>{{ $r['no'] }}</td>
                        <td>{{ $r['fecha'] }}</td>
                        <td class="izq nowrap"><b>{{ $r['marca'] }}</b></td>
                        <td>{{ $r['consecutivo'] }}</td>
                        <td>{{ $r['kg'] ? number_format($r['kg'], 1) : '' }}</td>
                        <td>{{ $r['linea'] }}</td>
                        <td>{{ $r['modulo'] }}</td>
                        <td class="persona">{{ $r['soldador'] }}</td>
                        <td class="persona">{{ $r['inspector'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['precalentamiento']])
                        @include('pdf.qal.partials.celda', ['c' => $r['limpieza_pasadas']])
                        <td>{!! $r['elementos'] !== null ? e(rtrim(rtrim(number_format($r['elementos'], 2, '.', ''), '0'), '.')) : '<span class="nulo">—</span>' !!}</td>
                        <td class="{{ $r['defectos'] ? 'a-def' : 'a-ok' }}">{{ $r['defectos'] }}</td>
                        <td class="txt">{{ $r['tipos'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['limpieza']])
                        @include('pdf.qal.partials.celda', ['c' => $r['etiqueta']])
                        <td class="{{ $r['inspeccion'] > 1 ? 'a-def' : '' }}">{{ $r['inspeccion'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                        <td class="txt">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="izq">TOTALES</td>
                    <td>{{ number_format($t['kg'], 1) }}</td>
                    <td colspan="4"></td>
                    <td colspan="2" class="izq">{{ $t['piezas'] }} pieza(s)</td>
                    <td>{{ $t['elementos'] ?: '—' }}</td>
                    <td>{{ $t['defectos'] }}</td>
                    <td class="izq">Defectos por elemento: {{ $t['por_elemento'] }}</td>
                    <td colspan="3"></td>
                    <td>{{ $t['liberadas'] }} L · {{ $t['rechazadas'] }} R{{ $t['pendientes'] ? ' · '.$t['pendientes'].' P' : '' }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</x-pdf.formato-qal>
