{{-- FC-STX-CA-07 · Medición de espesores de pintura. Ver App\Services\Qal\Formatos\Espesores. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th>No.</th>
                    <th style="width: 96pt;">Identificación de la pieza</th>
                    @for ($m = 1; $m <= $datos['columnas']; $m++)
                        <th>Med {{ $m }}</th>
                    @endfor
                    <th>Promedio</th>
                    <th>Req.<br>(mils)</th>
                    <th>Insp.</th>
                    <th>Aceptado /<br>Rechazado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td>{{ $r['no'] }}</td>
                        <td class="izq nowrap"><b>{{ $r['pieza'] }}</b></td>
                        @foreach ($r['mediciones'] as $medicion)
                            <td class="{{ $medicion['bajo'] ? 'bajo' : '' }}">{{ $medicion['texto'] }}</td>
                        @endforeach
                        <td><b>{{ $r['promedio'] }}</b></td>
                        <td>{{ $r['requerido'] }}</td>
                        <td class="{{ $r['inspeccion'] > 1 ? 'a-def' : '' }}">{{ $r['inspeccion'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-pdf.formato-qal>
