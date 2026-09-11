{{-- F-STX-CA-06 · Control diario de inspección de pintura. Ver App\Services\Qal\Formatos\ControlPintura. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th rowspan="2">Marca de pieza</th>
                    <th rowspan="2"># de<br>consec.</th>
                    <th rowspan="2">Cant.</th>
                    <th rowspan="2">Módulo</th>
                    <th colspan="3">Pruebas</th>
                    <th rowspan="2">Tipo de rechazo</th>
                    <th colspan="3">Revisiones</th>
                    <th rowspan="2">Kg / pieza</th>
                    <th rowspan="2">Estatus</th>
                    <th rowspan="2" style="width: 170pt;">Observaciones</th>
                </tr>
                <tr>
                    <th>Esp.</th>
                    <th>Vis.</th>
                    <th>Adh.</th>
                    <th>R1</th>
                    <th>R2</th>
                    <th>R3</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td class="izq nowrap"><b>{{ $r['marca'] }}</b></td>
                        <td>{{ $r['consecutivo'] }}</td>
                        <td>1</td>
                        <td>{{ $r['modulo'] }}</td>
                        @foreach ($r['pruebas'] as $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda])
                        @endforeach
                        <td class="txt">{{ $r['rechazo'] }}</td>
                        @foreach ($r['revisiones'] as $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda])
                        @endforeach
                        <td>{{ number_format($r['kg'], 3) }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                        <td class="txt">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="11" class="izq">TOTAL KILOS LIBERADOS</td>
                    <td>{{ number_format($datos['kg_liberados'], 3) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    @endif
</x-pdf.formato-qal>
