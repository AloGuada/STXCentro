{{--
    Un rótulo de columna girado, de abajo hacia arriba, como en el formato en
    papel: el FC-STX-CA-04 tiene 18 columnas de criterio y en horizontal no
    caben en una hoja.

    dompdf no conoce `writing-mode`, así que el texto va dentro de un SVG del
    tamaño exacto de la celda (en puntos, uno a uno con su viewBox). Los
    rótulos largos se parten en dos líneas apiladas para que la columna no
    crezca a lo alto sin necesidad.

    Uso: @include('pdf.qal.partials.rotulo', ['texto' => 'Placa de respaldo', 'alto' => 84])
--}}
@php
    $alto = $alto ?? 84;
    $tamano = 6.2;
    $lineas = [$texto];

    if (mb_strlen($texto) > 20) {
        $corte = mb_strrpos(mb_substr($texto, 0, intdiv(mb_strlen($texto), 2) + 5), ' ');

        if ($corte) {
            $lineas = [mb_substr($texto, 0, $corte), mb_substr($texto, $corte + 1)];
        }
    }

    $ancho = count($lineas) * ($tamano + 1.6) + 2;
    $textos = '';

    foreach ($lineas as $i => $linea) {
        $y = 1 + ($i + 1) * ($tamano + 1.2);
        $textos .= '<text transform="rotate(-90)" x="-'.($alto - 2).'" y="'.$y.'" font-size="'.$tamano.'" font-family="Helvetica" font-weight="bold">'
            .htmlspecialchars($linea, ENT_XML1).'</text>';
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$ancho.'" height="'.$alto.'" viewBox="0 0 '.$ancho.' '.$alto.'">'.$textos.'</svg>';
@endphp
<img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" style="width: {{ $ancho }}pt; height: {{ $alto }}pt;" alt="{{ $texto }}">
