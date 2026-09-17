{{-- FC-STX-CA-04 · Inspección visual de soldadura, una fila por marca. Ver App\Services\Qal\Formatos\VisualSoldadura. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        {{-- dompdf no respeta los anchos de <col>: los fijan las celdas del encabezado. --}}
        <table class="grid">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 64pt;">Marca</th>
                    <th rowspan="2" class="vert">@include('pdf.qal.partials.rotulo', ['texto' => 'Módulo de fabricación'])</th>
                    <th rowspan="2" class="vert">@include('pdf.qal.partials.rotulo', ['texto' => 'Cantidad de piezas'])</th>
                    <th colspan="{{ count($datos['antes']) }}" class="g0">Antes de soldar</th>
                    <th colspan="{{ count($datos['durante']) }}" class="g0">Durante la soldadura</th>
                    <th colspan="{{ count($datos['despues']) }}" class="g0">Después de soldar</th>
                    <th rowspan="2" class="g0" style="width: 150pt;">Observaciones</th>
                </tr>
                <tr>
                    @foreach ([$datos['antes'], $datos['durante'], $datos['despues']] as $grupo)
                        @foreach ($grupo as $i => $rotulo)
                            <th class="vert {{ $i === 0 ? 'g0' : '' }}">@include('pdf.qal.partials.rotulo', ['texto' => $rotulo])</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td class="izq nowrap"><b>{{ $r['marca'] }}</b></td>
                        <td class="txt">{{ $r['modulo'] }}</td>
                        <td>{{ $r['piezas'] }}</td>
                        @foreach ([$r['antes'], $r['durante'], $r['despues']] as $grupo)
                            @foreach ($grupo as $i => $celda)
                                @include('pdf.qal.partials.celda', ['c' => $celda, 'clase' => $i === 0 ? 'g0' : ''])
                            @endforeach
                        @endforeach
                        <td class="txt g0">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-pdf.formato-qal>
