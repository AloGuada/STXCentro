{{--
    El acta del conteo: qué decía el sistema, qué se encontró, y la diferencia.

    Es el formato que más importa que quede firmado, porque un ajuste mueve
    saldo sin que haya entrado ni salido nada — alguien tiene que responder por
    esa diferencia. Van tres firmas: quien contó, quien lo autorizó, y el jefe
    del almacén, que es quien queda con el faltante.

    El renglón contado exacto también se imprime, con su diferencia en cero. Es
    información: dice qué se revisó, no sólo qué falló.
--}}
<x-pdf.formato-alm
    titulo="AJUSTE DE INVENTARIO"
    subtitulo="ACTA DE CONTEO Y AJUSTE"
    :folio="$ajuste->folio">

    <table class="info-table">
        <tr>
            <td class="label">Fecha</td>
            <td>{{ $ajuste->fecha?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($ajuste->almacen?->clave ? $ajuste->almacen->clave.' - ' : '').($ajuste->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Motivo</td>
            <td>{{ ucfirst(str_replace('_', ' ', $ajuste->motivo?->value ?? $ajuste->motivo ?? '-')) }}</td>
            <td class="label">Registrado</td>
            <td>{{ $ajuste->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        @if ($ajuste->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $ajuste->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 14%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 13%;">Sistema</th>
                <th style="width: 13%;">Contado</th>
                <th style="width: 13%;">Diferencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ajuste->detalles as $d)
            <tr>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->articulo?->descripcion ?? '-' }}
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $d->cantidad_sistema, 2) }}</td>
                <td class="text-right">{{ number_format((float) $d->cantidad_contada, 2) }} {{ $d->articulo?->unidad }}</td>
                <td class="text-right">
                    {{ (float) $d->diferencia > 0 ? '+' : '' }}{{ number_format((float) $d->diferencia, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td colspan="2" class="text-right">{{ $ajuste->detalles->count() }} renglón(es) contado(s)</td>
                <td class="text-right">
                    @php $conDiferencia = $ajuste->detalles->filter(fn ($d) => abs((float) $d->diferencia) > 0.0001)->count(); @endphp
                    {{ $conDiferencia }} con diferencia
                </td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => [
        ['nombre' => null, 'rol' => 'Contó'],
        ['nombre' => $ajuste->autorizador?->name, 'rol' => 'Autorizó'],
        ['nombre' => null, 'rol' => 'Jefe de almacén'],
    ]])

    <div class="aviso">
        Las cantidades asentadas ya se aplicaron al kardex: este documento es el respaldo de esa aplicación, no una propuesta.
        Un ajuste no se corrige editándolo, se corrige con otro conteo.
    </div>
</x-pdf.formato-alm>
