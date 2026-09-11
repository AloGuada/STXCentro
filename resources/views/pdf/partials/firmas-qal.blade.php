{{--
    Las firmas de un formato de Calidad, en el orden del catálogo Firmantes.

    Cada lugar lleva arriba lo que es (Elaboró, Revisó…), la rúbrica estampada
    sobre la raya si la persona ya dibujó la suya en «Mi firma», y debajo nombre
    y cargo. Sin persona o sin rúbrica la raya sale en blanco para firmarse a
    mano: el lugar nunca se quita, el formato oficial lo trae.

    Uso:
        @include('pdf.partials.firmas-qal', ['firmas' => app(FirmasDeFormato::class)->para($inspector)])
--}}
<table style="width: 100%; margin-top: 18px; border-collapse: collapse; page-break-inside: avoid;">
    <tr>
        @foreach ($firmas as $firma)
        <td style="width: {{ round(100 / max(count($firmas), 1), 2) }}%; padding: 0 14px; text-align: center; vertical-align: bottom;">
            <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #555; margin-bottom: 2px;">{{ $firma['etiqueta'] }}</div>
            <div style="height: 42px; text-align: center;">
                @if ($firma['rubrica'])
                    <img src="{{ $firma['rubrica'] }}" alt="Firma" style="max-height: 42px; max-width: 150px;">
                @endif
            </div>
            <div style="border-top: 1px solid #333; padding-top: 3px;">
                {{-- Sin nombre, un espacio duro: vacío el renglón no mide nada y la raya quedaría más baja que las demás. --}}
                <div style="font-size: 8pt; font-weight: bold;">{!! $firma['nombre'] ? e($firma['nombre']) : '&nbsp;' !!}</div>
                <div style="font-size: 7pt; color: #555;">{{ $firma['cargo'] }}</div>
            </div>
        </td>
        @endforeach
    </tr>
</table>
