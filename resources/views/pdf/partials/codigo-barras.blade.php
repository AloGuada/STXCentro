{{--
    Sello de código de barras para PDFs (dompdf).

    dompdf no interpreta SVG —el logo ya se referencia como <img> por lo mismo—
    así que cada barra se dibuja como un bloque negro posicionado dentro de un
    contenedor relativo, que es lo único que rinde igual en pantalla y en papel.

    El fondo blanco es fijo y no heredado: el lector necesita el contraste del
    papel.

    Parámetros:
      $valor        folio a imprimir. Si no es Code 39, no se pinta nada.
      $modulo       ancho del módulo angosto en px (default 1 ≈ 0.26 mm, el
                    mínimo que un lector láser resuelve con holgura).
      $altura       alto de las barras en px (default 34).
      $mostrarTexto imprime el folio debajo, para poder teclearlo si el papel
                    se maltrata (default true).
--}}
@php
    $modulo = $modulo ?? 1;
    $altura = $altura ?? 34;
    $mostrarTexto = $mostrarTexto ?? true;
    $dibujo = \App\Support\Code39::dibujo((string) $valor);
    $ancho = $dibujo === null ? 0 : $dibujo['modulos'] * $modulo;
@endphp

@if ($dibujo !== null)
    <div style="width: {{ $ancho }}px; background-color: #ffffff; padding: 2px 0;">
        <div style="position: relative; width: {{ $ancho }}px; height: {{ $altura }}px;">
            @foreach ($dibujo['barras'] as $barra)
                <div style="position: absolute; top: 0; left: {{ $barra['x'] * $modulo }}px; width: {{ $barra['ancho'] * $modulo }}px; height: {{ $altura }}px; background-color: #000000;"></div>
            @endforeach
        </div>
        @if ($mostrarTexto)
            <div style="width: {{ $ancho }}px; text-align: center; font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; letter-spacing: 1.5px; color: #000000; margin-top: 1px;">{{ $valor }}</div>
        @endif
    </div>
@endif
