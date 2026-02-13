<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>F-STX-TI-17 Responsiva de Equipo Computo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            line-height: 1.5;
            padding: 10px 60px;
        }

        /* Header */
        .header-table {
            width: 100%;
            margin-bottom: 5px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-cell {
            width: 180px;
        }
        .logo-cell img {
            max-width: 170px;
        }
        .company-cell {
            text-align: center;
            vertical-align: middle;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
        }
        .company-url {
            font-size: 10px;
            color: #0563C1;
        }
        .company-dept {
            font-size: 10px;
            font-weight: bold;
        }
        .code-cell {
            width: 120px;
            text-align: center;
            vertical-align: top;
        }
        .code-box {
            border: 1px solid #000;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
        }
        .date-line {
            text-align: right;
            font-size: 11px;
            margin-bottom: 20px;
        }

        /* Title */
        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        /* Body text */
        .body-text {
            font-size: 12px;
            text-align: justify;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .body-text .underline-value {
            text-decoration: underline;
        }

        /* Description section */
        .description-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 15px;
        }
        .desc-line {
            font-size: 12px;
            margin-bottom: 8px;
        }
        .desc-line .label {
            font-weight: bold;
        }
        .desc-line .value {
            text-decoration: underline;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 60px;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 30px;
        }
        .sig-image {
            max-height: 70px;
            max-width: 200px;
        }
        .sig-name {
            font-weight: bold;
            font-size: 11px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #555;
            padding: 10px 40px;
        }
        .footer .footer-code {
            text-align: right;
            font-size: 9px;
            margin-top: 3px;
        }

        /* Page 2 images */
        .media-section {
            text-align: center;
            margin-top: 30px;
        }
        .media-section img {
            max-width: 90%;
            max-height: 280px;
            margin-bottom: 15px;
        }
        .media-label {
            font-size: 10px;
            color: #555;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    @php
        setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'es');
        $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $fecha = $asignacion->fecha_inicial ?? now();
        $diaSemana = $dias[$fecha->dayOfWeek];
        $dia = $fecha->day;
        $mes = $meses[$fecha->month - 1];
        $anio = $fecha->year;
        $fechaFormateada = "Kanasín, Yucatán a {$diaSemana}, {$dia} de {$mes} de {$anio}";

        $estadoTexto = $itemPrincipal
            ? (\App\Models\Sti\Item::ESTADO_LABELS[$itemPrincipal->estado] ?? $itemPrincipal->estado)
            : '-';
    @endphp

    {{-- ==================== PAGE 1 ==================== --}}

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(file_exists(public_path('images/logo-steelex.png')))
                    <img src="{{ public_path('images/logo-steelex.png') }}" alt="Steelex">
                @else
                    <strong style="font-size: 18px; color: #1a5276;">STEELEX</strong><br>
                    <span style="font-size: 8px; color: #666;">ESTRUCTURAS METALICAS</span>
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">TECNOLOGIAS DE LA INFORMACION</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    F-STX-TI-17<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="date-line">{{ $fechaFormateada }}</div>

    {{-- Title --}}
    <div class="title">RESPONSIVA DE EQUIPO COMPUTO</div>

    {{-- Intro paragraph --}}
    <p class="body-text">
        Por medio de la presente, hago constar que he recibido un(a)
        <span class="underline-value">{{ $itemPrincipal?->tipo?->descripcion ?? '________________' }}</span>
        para el desarrollo de mis funciones y actividades laborales en la empresa,
        <strong>TIM DEL MAYAB S.A. DE C.V.</strong>, con nombre comercial <em>STEELEX</em>.
    </p>

    {{-- Description section --}}
    <div class="description-title">Descripcion del equipo</div>

    <p class="desc-line">
        <span class="label">Equipo (marca y modelo):</span>
        <span class="value">{{ $equipo?->marca ?? '-' }} &ndash; {{ $equipo?->descripcion ?? '-' }}</span>
    </p>

    <p class="desc-line">
        <span class="label">Numero de serie:</span>
        <span class="value">{{ $equipo?->serie ?? '-' }}</span>
    </p>

    @if($itemsAccesorios->count() > 0)
        @foreach($itemsAccesorios as $i => $acc)
        <p class="desc-line">
            <span class="label">{{ $i === 0 ? 'Accesorio:' : '' }}</span>
            <span class="value">{{ $acc->tipo?->descripcion ?? '' }} {{ $acc->descripcion }}{{ $acc->no_serie ? ' (S/N: '.$acc->no_serie.')' : '' }}</span>.
        </p>
        @endforeach
    @else
    <p class="desc-line">
        <span class="label">Accesorio:</span>
        <span class="value">-</span>.
    </p>
    @endif

    <p class="desc-line">
        <span class="label">Estado:</span>
        <span class="value">{{ $estadoTexto }}</span>.
    </p>

    {{-- Responsibility paragraph --}}
    <p class="body-text" style="margin-top: 25px;">
        <strong>Yo como receptor
        <span class="underline-value">{{ $asignacion->empleado }}</span>,
        con numero de trabajador
        <span class="underline-value">{{ $asignacion->no_empleado }}</span>,
        asumo la responsabilidad y el cuidado de dicho equipo y me comprometo a utilizarlo
        estrictamente para uso de las labores en el area de
        <span class="underline-value">{{ $asignacion->departamento?->descripcion ?? '-' }}</span>.</strong>
    </p>

    <p class="body-text">
        <strong>Aquellos danos que sean causados por una mala practica, instalacion de software no
        autorizado o imprudencia mia, asumire las repercusiones que de ello se deriven y los
        gastos que a si se produzcan por lo antes descrito.</strong>
    </p>

    {{-- Signatures --}}
    <table class="signatures-table">
        <tr>
            <td>
                @if($asignacion->firma_empleado)
                    <img src="{{ $asignacion->firma_empleado }}" class="sig-image" alt="Firma empleado"><br>
                @else
                    <div style="height: 70px;"></div>
                @endif
                <div class="sig-name">{{ $asignacion->empleado }}</div>
            </td>
            <td>
                @if($asignacion->firma_ti)
                    <img src="{{ $asignacion->firma_ti }}" class="sig-image" alt="Firma TI"><br>
                @else
                    <div style="height: 70px;"></div>
                @endif
                <div class="sig-name">{{ $asignacion->nombre_ti }}</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">F-STX-TI-17<br>Revision: 00</div>
    </div>

    {{-- ==================== PAGE 2: Imagenes ==================== --}}
    <div style="page-break-before: always;"></div>

    {{-- Header (repeated) --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(file_exists(public_path('images/logo-steelex.png')))
                    <img src="{{ public_path('images/logo-steelex.png') }}" alt="Steelex">
                @else
                    <strong style="font-size: 18px; color: #1a5276;">STEELEX</strong><br>
                    <span style="font-size: 8px; color: #666;">ESTRUCTURAS METALICAS</span>
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">TECNOLOGIAS DE LA INFORMACION</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    F-STX-TI-17<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="date-line">{{ $fechaFormateada }}</div>

    {{-- Principal item media --}}
    <div class="media-section">
        @if($itemPrincipal && $itemPrincipal->media && $itemPrincipal->media->count() > 0)
            @foreach($itemPrincipal->media as $media)
                @php
                    $mediaPath = public_path('storage/' . $media->path);
                @endphp
                @if(file_exists($mediaPath) && str_starts_with($media->mime, 'image/'))
                    <img src="{{ $mediaPath }}" alt="{{ $media->descripcion }}"><br>
                    <div class="media-label">Principal: {{ $itemPrincipal->descripcion }}</div>
                @endif
            @endforeach
        @endif

        {{-- Accessory items media --}}
        @foreach($itemsAccesorios as $accesorio)
            @if($accesorio->media && $accesorio->media->count() > 0)
                @foreach($accesorio->media as $media)
                    @php
                        $mediaPath = public_path('storage/' . $media->path);
                    @endphp
                    @if(file_exists($mediaPath) && str_starts_with($media->mime, 'image/'))
                        <img src="{{ $mediaPath }}" alt="{{ $media->descripcion }}"><br>
                        <div class="media-label">Accesorio: {{ $accesorio->descripcion }}</div>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if((!$itemPrincipal || !$itemPrincipal->media || $itemPrincipal->media->count() === 0) && $itemsAccesorios->every(fn ($a) => !$a->media || $a->media->count() === 0))
            <p style="color: #999; margin-top: 50px;">Sin imagenes registradas</p>
        @endif
    </div>
</body>
</html>
