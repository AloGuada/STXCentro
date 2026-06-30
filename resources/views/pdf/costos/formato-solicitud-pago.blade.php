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

        /* Firma del primer nivel bajo los datos */
        .firma-primer-nivel {
            width: 45%;
            margin: 5px 0 20px;
        }
        .firma-primer-nivel td {
            text-align: center;
            vertical-align: bottom;
            padding: 0 15px;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 40px;
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
            <td class="label">Solicitante</td>
            <td>{{ $solicitud->solicitante?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td colspan="3">{{ $solicitud->proveedor?->razon_social ?? 'Sin proveedor' }}</td>
        </tr>
        <tr>
            <td class="label">Tipo Solicitud</td>
            <td>{{ $solicitud->tipoSolicitud?->titulo ?? '-' }}</td>
            <td class="label">Método de pago</td>
            <td style="text-transform: capitalize;">{{ $solicitud->tipo_pago }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de pago</td>
            <td>{{ $solicitud->fecha_pago_solicitada?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Moneda</td>
            <td>{{ strtoupper($solicitud->tipo_moneda ?? 'mxn') }}</td>
        </tr>
        <tr>
            <td class="label">Concepto</td>
            <td colspan="3">{{ $solicitud->concepto }}</td>
        </tr>
    </table>

    {{-- Firma del primer nivel (justo bajo los datos) --}}
    @php $primeraFirma = $firmasPdf->first(); @endphp
    @if($primeraFirma)
    <table class="firma-primer-nivel">
        <tr>
            <td>
                @if($primeraFirma->aprobada && $primeraFirma->aprobador?->firma_path && file_exists(storage_path('app/public/' . $primeraFirma->aprobador->firma_path)))
                    <img class="sig-img" src="{{ storage_path('app/public/' . $primeraFirma->aprobador->firma_path) }}" alt="Firma">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">{{ $primeraFirma->aprobada ? $primeraFirma->aprobador?->name : ($primeraFirma->candidatos->isNotEmpty() ? $primeraFirma->candidatos->implode(' / ') : 'Pendiente') }}</div>
                <div class="sig-role">{{ $primeraFirma->aprobada ? 'Firmado '.$primeraFirma->fecha : 'Pendiente de firma' }}</div>
            </td>
        </tr>
    </table>
    @endif

    {{-- Detalles --}}
    @if($solicitud->detalles && $solicitud->detalles->count() > 0)
    <table class="detalles-table">
        <thead>
            <tr>
                <th>Centro de Costos</th>
                <th>Concepto</th>
                <th>Cantidad</th>
                <th>P. Unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($solicitud->detalles as $detalle)
            <tr>
                <td>
                    {{ $detalle->obraRubro?->rubro?->codigo ?? '-' }}
                    @if($detalle->obraRubro?->rubro?->descripcion)
                        - {{ $detalle->obraRubro->rubro->descripcion }}
                    @endif
                </td>
                <td>{{ $detalle->concepto }}</td>
                <td class="text-right">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right">${{ number_format($solicitud->monto_total, 2) }}</td>
            </tr>
            <tr class="letras-row">
                <td colspan="5">{{ \App\Support\NumeroALetras::convertir((float) $solicitud->monto_total, $solicitud->tipo_moneda ?? 'mxn') }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <table class="detalles-table">
        <tbody>
            <tr class="total-row">
                <td class="text-right" style="width: 75%;">TOTAL</td>
                <td class="text-right">${{ number_format($solicitud->monto_total, 2) }}</td>
            </tr>
            <tr class="letras-row">
                <td colspan="2">{{ \App\Support\NumeroALetras::convertir((float) $solicitud->monto_total, $solicitud->tipo_moneda ?? 'mxn') }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Signatures (niveles restantes; el primero va bajo los datos) --}}
    @php $firmasRestantes = $firmasPdf->slice(1); @endphp
    @if($firmasRestantes->isNotEmpty())
    <table class="signatures-table">
        <tr>
            @foreach($firmasRestantes as $firma)
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

    {{-- Footer --}}
    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">F-STX-COSTOS-01<br>Revision: 00</div>
    </div>
</body>
</html>
