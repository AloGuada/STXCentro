{{-- F-STX-CA-09 · Registro de inspección de armado y vestido. Ver App\Services\Qal\Formatos\ArmadoVestido. --}}
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
                    <th rowspan="2">Inspector</th>
                    <th colspan="{{ count(\App\Services\Qal\Formatos\ArmadoVestido::PREPARACION) }}">Preparación de juntas</th>
                    <th colspan="{{ count(\App\Services\Qal\Formatos\ArmadoVestido::ARMADO) }}">Armado</th>
                    <th rowspan="2">Falta<br>vestido</th>
                    <th rowspan="2">Insp.</th>
                    <th rowspan="2">Resultado</th>
                    <th rowspan="2">Observaciones</th>
                </tr>
                <tr>
                    @foreach ([...\App\Services\Qal\Formatos\ArmadoVestido::PREPARACION, ...\App\Services\Qal\Formatos\ArmadoVestido::ARMADO] as $rotulo)
                        <th>{{ $rotulo }}</th>
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
                        <td>{{ $r['kg'] ? number_format($r['kg'], 1) : '' }}</td>
                        <td>{{ $r['linea'] }}</td>
                        <td>{{ $r['modulo'] }}</td>
                        <td class="persona">{{ $r['inspector'] }}</td>
                        @foreach ([...$r['preparacion'], ...$r['armado']] as $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda])
                        @endforeach
                        <td class="{{ $r['falta_vestido'] ? 'a-def' : 'nulo' }}">{{ $r['falta_vestido'] }}</td>
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
                    <td colspan="3"></td>
                    <td colspan="10" class="izq">{{ $t['piezas'] }} pieza(s) inspeccionada(s)</td>
                    <td>{{ $t['falta_vestido'] }}</td>
                    <td></td>
                    <td>{{ $t['aceptadas'] }} A · {{ $t['rechazadas'] }} R</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</x-pdf.formato-qal>
