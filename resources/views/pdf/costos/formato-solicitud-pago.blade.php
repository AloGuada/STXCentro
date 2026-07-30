<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>F-STX-COSTOS-01 Solicitud de Pago</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
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
        .company-address {
            font-size: 8.5px;
            line-height: 1.35;
            margin-top: 3px;
        }
        .code-cell {
            width: 140px;
            text-align: center;
            vertical-align: top;
        }
        .code-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .code-box {
            border: 1px solid #000;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
        }

        /* Title */
        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 15px 0;
        }

        /* Info grid */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 10px;
        }
        .info-table .label {
            font-weight: bold;
            background-color: #f0f0f0;
            width: 25%;
        }
        .info-table .letras {
            font-style: italic;
            text-transform: uppercase;
            font-size: 9px;
        }

        /* Detalles table */
        .detalles-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .detalles-table th {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
            background-color: #f0f0f0;
            text-align: center;
        }
        .detalles-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 10px;
        }
        .detalles-table .text-right {
            text-align: right;
        }
        .detalles-table .total-row td {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .detalles-table .letras-row td {
            border: 1px solid #000;
            font-size: 9px;
            font-style: italic;
            text-transform: uppercase;
            padding: 4px 8px;
        }

        /* Notas al pie, después de las firmas */
        .notas-table {
            margin-top: 25px;
            margin-bottom: 0;
        }
        .notas-table td {
            font-size: 9px;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 10px;
        }
        /* Primera área (Costos), bajo los datos: separa del detalle siguiente y
           ocupa solo la mitad de la fila. */
        .firmas-costos {
            width: 50%;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .signatures-table td {
            text-align: center;
            vertical-align: bottom;
            padding: 0 15px;
        }
        .sig-img {
            height: 60px;
            margin-bottom: 5px;
        }
        .sig-placeholder {
            height: 60px;
        }
        .sig-name {
            font-weight: bold;
            font-size: 10px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .sig-role {
            font-size: 9px;
            color: #555;
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
    </style>
</head>
<body>
    @php
        $dias = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $fecha = $solicitud->created_at ?? now();
        $diaSemana = $dias[$fecha->dayOfWeek];
        $dia = $fecha->day;
        $mes = $meses[$fecha->month - 1];
        $anio = $fecha->year;
        $fechaFormateada = "Kanasin, Yucatan a {$diaSemana}, {$dia} de {$mes} de {$anio}";
    @endphp

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo')
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-address">
                    Carretera Mérida KM1, Lote G1,G2,G3, Tablaje Catastral 16704<br>
                    Kanasín, Yucatán C.P. 97370<br>
                    Tel: 999-454-06-00<br>
                    E-mail: facturacion.almacen@steelex.com.mx<br>
                    R.F.C. TMA9405205F5<br>
                    www.steelex.com.mx
                </div>
            </td>
            <td class="code-cell">
                <div class="code-title">SOLICITUD DE PAGO</div>
                <div class="code-box">
                    F-STX-COSTOS-01<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="title">SOLICITUD DE PAGO</div>

    {{-- Info --}}
    <table class="info-table">
        <tr>
            <td class="label">Folio</td>
            <td>{{ $solicitud->folio }}</td>
            <td class="label">Fecha de elaboración</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $solicitud->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Elaboró</td>
            <td>{{ $solicitud->solicitante?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Beneficiario</td>
            <td colspan="3"><strong>{{ $solicitud->proveedor?->razon_social ?? 'Sin proveedor' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Fecha de pago</td>
            <td>{{ $solicitud->fecha_pago_solicitada?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Método de pago</td>
            <td style="text-transform: capitalize;">{{ $solicitud->tipo_pago }}</td>
        </tr>
        @php $codMon = strtolower($solicitud->tipo_moneda ?? 'mxn') === 'mxn' ? '' : ' '.strtoupper($solicitud->tipo_moneda); @endphp
        <tr>
            <td class="label">Total a pagar</td>
            <td><strong>${{ number_format($solicitud->monto_total, 2) }}{{ $codMon }}</strong></td>
            <td class="label">Moneda</td>
            <td>{{ strtoupper($solicitud->tipo_moneda ?? 'mxn') }}</td>
        </tr>
        <tr>
            <td class="label">Cantidad en letra</td>
            <td colspan="3" class="letras">{{ \App\Support\NumeroALetras::convertir((float) $solicitud->monto_total, $solicitud->tipo_moneda ?? 'mxn') }}</td>
        </tr>
        <tr>
            <td class="label">Concepto</td>
            <td colspan="3">{{ $solicitud->concepto }}</td>
        </tr>
    </table>

    {{-- Primera área de firmas: SOLO aprobaciones de Costos (niveles marcados
         como "Firma de Costos"). No aplica a solicitudes generadas por OC, que
         usan el bloque fijo de dos firmas al final. Si no hay firmas de Costos,
         no se dibuja el bloque. --}}
    @php $firmasCostos = ($firmasOc ?? null) ? collect() : $firmasPdf->filter(fn ($f) => $f->permiso->es_costos ?? false)->values(); @endphp
    @if($firmasCostos->isNotEmpty())
    <table class="signatures-table firmas-costos">
        <tr>
            @foreach($firmasCostos as $firma)
            <td>
                @if($firma->aprobada && $firma->aprobador?->firma_path && file_exists(storage_path('app/public/' . $firma->aprobador->firma_path)))
                    <img class="sig-img" src="{{ storage_path('app/public/' . $firma->aprobador->firma_path) }}" alt="Firma">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">{{ $firma->aprobada ? $firma->aprobador?->name : ($firma->candidatos->isNotEmpty() ? $firma->candidatos->implode(' / ') : 'Pendiente') }}</div>
                <div class="sig-role">{{ $firma->aprobada ? 'Firmado '.$firma->fecha : 'Pendiente de firma' }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @endif

    {{-- Detalles --}}
    @if($solicitud->detalles && $solicitud->detalles->count() > 0)
    @php $cargadoCostos = (float) $solicitud->detalles->sum('subtotal'); @endphp
    <table class="detalles-table">
        <thead>
            <tr>
                <th>Obra</th>
                <th>Centro de Costos</th>
                <th>Cantidad</th>
                <th>P. Unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($solicitud->detalles as $detalle)
            @php $obra = $detalle->obraRubro?->obra; @endphp
            <tr>
                <td>{{ $obra ? trim(($obra->no ? $obra->no.' - ' : '').$obra->descripcion) : '-' }}</td>
                <td>{{ $detalle->obraRubro?->rubro?->descripcion ?? '-' }}</td>
                <td class="text-right">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right">CARGADO A COSTOS</td>
                <td class="text-right">${{ number_format($cargadoCostos, 2) }}{{ $codMon }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <table class="detalles-table">
        <tbody>
            <tr class="total-row">
                <td class="text-right" style="width: 75%;">TOTAL</td>
                <td class="text-right">${{ number_format($solicitud->monto_total, 2) }}{{ $codMon }}</td>
            </tr>
            <tr class="letras-row">
                <td colspan="2">{{ \App\Support\NumeroALetras::convertir((float) $solicitud->monto_total, $solicitud->tipo_moneda ?? 'mxn') }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Firmas de solicitud generada por OC: dos espacios fijos (elaboró +
         gerente de compras). --}}
    @if($firmasOc ?? null)
    <table class="signatures-table">
        <tr>
            @foreach($firmasOc as $firma)
            <td style="width: 50%;">
                @if($firma->firma_path && file_exists(storage_path('app/public/' . $firma->firma_path)))
                    <img class="sig-img" src="{{ storage_path('app/public/' . $firma->firma_path) }}" alt="Firma">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">{{ $firma->nombre }}</div>
                <div class="sig-role">{{ $firma->fecha ? 'Firmado '.$firma->fecha : $firma->rol }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @else
    {{-- Segunda área de firmas: SOLO aprobaciones que NO son de Costos (las de
         Costos van en el primer bloque, bajo los datos). Si no hay, no se dibuja. --}}
    @php $firmasNoCostos = $firmasPdf->filter(fn ($f) => ! ($f->permiso->es_costos ?? false))->values(); @endphp
    @if($firmasNoCostos->isNotEmpty())
    <table class="signatures-table">
        <tr>
            @foreach($firmasNoCostos as $firma)
            <td>
                @if($firma->aprobada && $firma->aprobador?->firma_path && file_exists(storage_path('app/public/' . $firma->aprobador->firma_path)))
                    <img class="sig-img" src="{{ storage_path('app/public/' . $firma->aprobador->firma_path) }}" alt="Firma">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">{{ $firma->aprobada ? $firma->aprobador?->name : ($firma->candidatos->isNotEmpty() ? $firma->candidatos->implode(' / ') : 'Pendiente') }}</div>
                <div class="sig-role">{{ $firma->aprobada ? 'Firmado '.$firma->fecha : 'Pendiente de firma' }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @endif
    @endif

    {{-- Notas al pie: van después de las firmas, para no empujarlas a otra
         página. Se dibuja el bloque solo si hay algo que decir. --}}
    @php
        $textosArchivos = ($solicitud->archivos ?? collect())
            ->filter(fn ($a) => filled($a->texto_adicional))
            ->map(fn ($a) => [
                'etiqueta' => $a->documento?->texto ?: $a->documento?->titulo ?: 'Documento',
                'valor' => $a->texto_adicional,
            ])
            ->values();
    @endphp
    @if(filled($solicitud->comentarios) || $textosArchivos->isNotEmpty())
    <table class="info-table notas-table">
        @if(filled($solicitud->comentarios))
        <tr>
            <td class="label">Notas</td>
            <td>{{ $solicitud->comentarios }}</td>
        </tr>
        @endif
        @foreach($textosArchivos as $texto)
        <tr>
            <td class="label">{{ $texto['etiqueta'] }}</td>
            <td>{{ $texto['valor'] }}</td>
        </tr>
        @endforeach
    </table>
    @endif
</body>
</html>
