{{--
    El armazón de los formatos F-STX-* de Calidad, en carta horizontal.

    El encabezado —logo, título, caja del código y los datos generales— va fijo
    y se repite en cada hoja, como en el papel: una hoja suelta del dosier tiene
    que decir de qué obra y de qué formato es. El «Hoja n de N» lo escribe
    `GeneradorDeFormato` en el hueco de la tercera línea de la caja del código.

    Debajo del título va el distintivo de destino. No es adorno: evita que un
    control interno acabe en el dosier del cliente.

    Al final, la nota de re-inspecciones, la leyenda y las firmas en el orden
    del catálogo Firmantes.

    Uso:
        <x-pdf.formato-qal :formato="$formato" :generales="$datos['generales']" :firmas="$firmas"
                           :leyenda="$datos['leyenda']" :nota="$datos['nota']">
            ... la tabla del formato ...
        </x-pdf.formato-qal>
--}}
@props(['formato', 'generales' => [], 'firmas' => [], 'leyenda' => null, 'nota' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $formato->codigo() }} · {{ $formato->titulo() }}</title>
    <style>
        @page { margin: 100pt 22pt 30pt 22pt; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7.5pt; color: #000; margin: 0; }

        .encabezado { position: fixed; top: -84pt; left: 0; right: 0; }
        table.cab { width: 100%; border-collapse: collapse; }
        table.cab td { border: 1.5pt solid #000; vertical-align: middle; }
        .cab .logo { width: 118pt; height: 50pt; text-align: center; }
        .cab .titulo { text-align: center; padding: 3pt 8pt; }
        .cab .t1 { font-size: 11.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt; }
        .cab .t2 { font-size: 7.5pt; color: #444; margin-top: 1pt; }
        .cab .destino { display: inline-block; margin-top: 3pt; padding: 1pt 6pt; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.3pt; }
        .cab .destino.dosier { background: #e5f3ea; color: #1f7a3d; border: 0.8pt solid #1f7a3d; }
        .cab .destino.interno { background: #fdf6e3; color: #8a6d00; border: 0.8pt solid #8a6d00; }
        .cab .codigo { width: 128pt; padding: 3pt 8pt; font-size: 7.5pt; line-height: 11pt; }
        .cab .codigo b { font-size: 9pt; }
        table.generales { width: 100%; border-collapse: collapse; border: 1.5pt solid #000; border-top: 0; }
        table.generales td { padding: 2pt 6pt; font-size: 7.2pt; border-right: 0.6pt solid #888; }
        table.generales td:last-child { border-right: 0; }

        .pie { position: fixed; bottom: -18pt; left: 0; right: 0; font-size: 6pt; color: #777; }

        table.grid { width: 100%; border-collapse: collapse; border: 1.5pt solid #000; }
        table.grid th, table.grid td { border: 0.8pt solid #000; padding: 1.5pt 2pt; text-align: center; font-size: 7pt; }
        table.grid thead th { background: #dbe2ee; font-size: 6.4pt; line-height: 1.1; }
        table.grid thead th.vert { padding: 2pt 1pt; vertical-align: bottom; }
        table.grid tfoot td { background: #f1f4f9; font-weight: bold; border-top: 1.5pt solid #000; }
        table.grid td.izq { text-align: left; }
        table.grid td.nowrap { white-space: nowrap; }
        table.grid td.txt { text-align: left; font-size: 6.2pt; }
        table.grid td.persona { font-size: 6.2pt; }
        table.grid .g0 { border-left: 1.5pt solid #000; }
        table.grid tbody tr { page-break-inside: avoid; }

        .a-ok { color: #1f7a3d; font-weight: bold; }
        .a-def { color: #b3261e; font-weight: bold; }
        .a-pen { color: #8a6d00; font-weight: bold; }
        .nulo { color: #999; }
        /* ✓ y ✗ no existen en la Helvetica del PDF: van en DejaVu, que dompdf trae. */
        .sim { font-family: 'DejaVu Sans', sans-serif; }
        table.grid td.bajo { background: #fff7d6; }
        .aviso { margin-top: 5pt; padding: 3pt 6pt; background: #fdecea; border: 0.6pt solid #e3a39c; font-size: 6.8pt; }

        /* Hojas de evidencia: seis recuadros en 3 × 2, como el acetato escaneado. */
        .evidencias { page-break-before: always; }
        .ev-titulo { width: 100%; border-collapse: collapse; border: 1.5pt solid #000; margin-bottom: 6pt; }
        .ev-titulo td { padding: 3pt 8pt; font-size: 8pt; }
        table.ev-rejilla { width: 100%; border-collapse: collapse; }
        table.ev-rejilla td { width: 33.3%; height: 196pt; padding: 3pt 10pt; vertical-align: top; text-align: center; }
        table.ev-rejilla tr { page-break-inside: avoid; }
        .ev-rotulo { font-size: 9pt; font-weight: bold; padding-bottom: 3pt; }
        .ev-marco { height: 176pt; border: 0.8pt solid #000; text-align: center; }
        .ev-marco img { max-width: 100%; max-height: 174pt; }
        .ev-marco.vacio { border: 0.8pt dashed #bbb; }
        .ev-pdf { padding-top: 80pt; font-size: 8pt; color: #333; }

        .nota { margin-top: 5pt; padding: 3pt 6pt; background: #fdf6e3; border: 0.6pt solid #d9c27a; font-size: 6.8pt; }
        .leyenda { margin-top: 4pt; font-size: 6.8pt; color: #333; }
        .vacio { margin-top: 30pt; padding: 24pt; text-align: center; border: 1pt dashed #999; font-size: 9pt; color: #555; }
    </style>
</head>
<body>
    <div class="encabezado">
        <table class="cab">
            <tr>
                <td class="logo">@include('pdf.partials.logo', ['width' => 108])</td>
                <td class="titulo">
                    <div class="t1">{{ $formato->titulo() }}</div>
                    <div class="t2">{{ $formato->subtitulo() }}</div>
                    <div class="destino {{ $formato->destino() }}">
                        {{ $formato->paraElDosier() ? 'PARA EL DOSIER' : 'USO INTERNO · NO ENVIAR AL CLIENTE' }}
                    </div>
                </td>
                <td class="codigo"><b>{{ $formato->codigo() }}</b><br>{{ $formato->revision() }}<br>&nbsp;</td>
            </tr>
        </table>
        <table class="generales">
            <tr>
                @foreach ($generales as $etiqueta => $valor)
                    <td><b>{{ $etiqueta }}:</b> {{ $valor }}</td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="pie">Generado el {{ now()->format('d/m/Y H:i') }} · Sistema de Calidad Steelex</div>

    {{ $slot }}

    @if ($nota)
        <div class="nota">{{ $nota }}</div>
    @endif
    @if ($leyenda)
        {{-- La leyenda va en Helvetica, que no trae ✓ ✗ ≥: esos signos se escriben en DejaVu. --}}
        <div class="leyenda">{!! preg_replace('/([✓✗≥≤])/u', '<span class="sim">$1</span>', e($leyenda)) !!}</div>
    @endif

    @include('pdf.partials.firmas-qal', ['firmas' => $firmas])

    {{-- Lo que va después de firmar, en hojas completas: las evidencias de adherencia. --}}
    @isset($anexos)
        {{ $anexos }}
    @endisset
</body>
</html>
