{{--
    La hoja de conteo: la lista que se camina y se llena a mano.

    No trae el saldo del sistema, a propósito. Un número a la vista es una
    respuesta sugerida, y el conteo sirve como control justamente porque quien
    cuenta no sabe cuánto debería haber. La comparación se hace después, en la
    pantalla, ya con lo contado escrito.

    Dos firmas: quien contó y quien revisó. La del jefe de almacén va en el
    ajuste, que es el documento que mueve saldo.
--}}
<x-pdf.formato-alm
    titulo="INVENTARIO CÍCLICO"
    subtitulo="HOJA DE CONTEO"
    :folio="$conteo->folio">

    <table class="info-table">
        <tr>
            <td class="label">Fecha programada</td>
            <td>{{ $conteo->fecha_programada?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($conteo->almacen?->clave ? $conteo->almacen->clave.' - ' : '').($conteo->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Origen</td>
            <td>{{ $conteo->origen?->etiqueta() ?? '-' }}</td>
            <td class="label">Responsable</td>
            <td>{{ $conteo->responsable?->name ?? '________________________' }}</td>
        </tr>
        @if ($conteo->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $conteo->observaciones }}</td>
        </tr>
        @endif
    </table>

    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 13%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 8%;">Unidad</th>
                <th style="width: 18%;">Ubicación</th>
                <th style="width: 13%;">Contado</th>
                <th style="width: 14%;">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($conteo->detalles as $d)
            <tr>
                <td class="text-right">{{ $d->orden }}</td>
                <td>{{ $d->articulo?->codigo ?? '-' }}</td>
                <td>{{ $d->articulo?->descripcion ?? '-' }}</td>
                <td>{{ $d->articulo?->unidad ?? '' }}</td>
                <td>{{ $d->existencia?->ubicacion?->ruta() ?? '' }}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td colspan="5">{{ $conteo->detalles->count() }} artículo(s) por contar</td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.firmas', ['firmas' => $firmas])

    <div class="aviso">
        Anota lo que encontraste, no lo que debería haber. La hoja no trae el saldo del sistema a propósito:
        la comparación se hace al capturar, y las diferencias se corrigen con un ajuste, nunca en esta hoja.
    </div>
</x-pdf.formato-alm>
