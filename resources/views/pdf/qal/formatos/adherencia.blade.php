{{-- F-STX-CA-08 · Prueba de adherencia y sus hojas de evidencia. Ver App\Services\Qal\Formatos\PruebaDeAdherencia. --}}
<x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas" :leyenda="$datos['leyenda']" :nota="$datos['nota']">
    @if ($datos['vacio'])
        <div class="vacio">{{ $datos['vacio'] }}</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Identificación de la pieza</th>
                    {{-- El método es de cada tira: ASTM D3359 lo decide por el
                         espesor de la película, que cambia de una a otra. --}}
                    <th>Mét. 1</th>
                    <th>Tira 1</th>
                    <th>Mét. 2</th>
                    <th>Tira 2</th>
                    <th>Mét. 3</th>
                    <th>Tira 3</th>
                    <th>Insp.</th>
                    <th>Aceptado /<br>Rechazado</th>
                    <th style="width: 200pt;">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['renglones'] as $r)
                    <tr>
                        <td>{{ $r['no'] }}</td>
                        <td class="izq nowrap"><b>{{ $r['pieza'] }}</b></td>
                        @foreach ($r['tiras'] as $tira)
                            <td>{{ $tira['metodo'] }}</td>
                            <td><b>{{ $tira['clasificacion'] }}</b></td>
                        @endforeach
                        <td class="{{ $r['inspeccion'] > 1 ? 'a-def' : '' }}">{{ $r['inspeccion'] }}</td>
                        @include('pdf.qal.partials.celda', ['c' => $r['resultado']])
                        <td class="txt">{{ $r['observaciones'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <x-slot:anexos>
        @foreach ($datos['evidencias'] as $hoja)
            <div class="evidencias">
                <table class="ev-titulo">
                    <tr>
                        <td><b>EVIDENCIA FOTOGRÁFICA · PRUEBA DE ADHERENCIA</b> · ASTM D3359</td>
                        <td style="text-align: center;">Fecha: {{ $hoja['fecha'] }}</td>
                        <td style="text-align: right;"><b>Evidencia {{ $hoja['numero'] }} de {{ count($datos['evidencias']) }}</b></td>
                    </tr>
                </table>
                <table class="ev-rejilla">
                    @foreach (array_chunk($hoja['marcos'], 3) as $fila)
                        <tr>
                            @foreach ($fila as $marco)
                                <td>
                                    @if ($marco)
                                        <div class="ev-rotulo">{{ $marco['rotulo'] }}</div>
                                        <div class="ev-marco">
                                            @if ($marco['ruta'])
                                                <img src="{{ $marco['ruta'] }}" alt="{{ $marco['rotulo'] }}">
                                            @else
                                                <div class="ev-pdf">{{ $marco['pdf'] ?? 'Foto no encontrada' }}<br>(consultar en el sistema)</div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="ev-rotulo">&nbsp;</div>
                                        <div class="ev-marco vacio"></div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    </x-slot:anexos>
</x-pdf.formato-qal>
