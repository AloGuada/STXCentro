{{-- F-STX-CA-04 · Reporte de mapeo junta por junta de una pieza. Ver App\Services\Qal\Formatos\MapeoDeJuntas. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        <table class="grid" style="table-layout: fixed;">
            <colgroup>
                <col style="width: 7%;">
                <col style="width: 7%;">
                <col style="width: 7%;">
                @foreach ($datos['puntos'] as $punto)
                    <col>
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th>Junta</th>
                    <th>Clave<br>soldador</th>
                    <th>Tipo de<br>junta</th>
                    @foreach ($datos['puntos'] as $i => $punto)
                        <th class="vert {{ $i === 0 ? 'g0' : '' }}">@include('pdf.qal.partials.rotulo', ['texto' => $punto, 'alto' => 70])</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td><b>{{ $r['junta'] }}</b></td>
                        <td>{{ $r['soldador'] }}</td>
                        <td>{{ $r['tipo'] }}</td>
                        @foreach ($r['celdas'] as $i => $celda)
                            @include('pdf.qal.partials.celda', ['c' => $celda, 'clase' => $i === 0 ? 'g0' : ''])
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-pdf.formato-qal>
