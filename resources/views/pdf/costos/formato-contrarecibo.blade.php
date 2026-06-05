<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrarecibo {{ $factura->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; line-height: 1.4; padding: 10px 60px; }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 180px; }
        .logo-cell img { max-width: 170px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-url { font-size: 10px; color: #0563C1; }
        .company-dept { font-size: 10px; font-weight: bold; }
        .code-cell { width: 120px; text-align: center; vertical-align: top; }
        .code-box { border: 1px solid #000; padding: 4px 10px; font-size: 10px; font-weight: bold; display: inline-block; }
        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 15px 0; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { border: 1px solid #000; padding: 5px 10px; font-size: 11px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 30%; }
        .acuse { margin-top: 60px; text-align: center; }
        .acuse-line { border-top: 1px solid #000; width: 300px; margin: 0 auto; padding-top: 5px; font-size: 10px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #555; padding: 10px 40px; }
    </style>
</head>
<body>
    @php
        $fecha = now();
    @endphp

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo')
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">COSTOS</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    F-STX-COSTOS-CR<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="title">CONTRARECIBO</div>

    <table class="info-table">
        <tr>
            <td class="label">Fecha</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td>{{ $oc->proveedor?->razon_social ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">RFC Proveedor</td>
            <td>{{ $oc->proveedor?->rfc ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Orden de Compra</td>
            <td>{{ $oc->folio }}</td>
        </tr>
        <tr>
            <td class="label">Factura</td>
            <td>{{ $factura->folio }} {{ $factura->folio_fiscal ? '(Fiscal: ' . $factura->folio_fiscal . ')' : '' }}</td>
        </tr>
        <tr>
            <td class="label">UUID Fiscal</td>
            <td>{{ $factura->uuid_fiscal ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Subtotal Factura</td>
            <td>${{ number_format($factura->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="label">IVA</td>
            <td>${{ number_format($factura->iva, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total Factura</td>
            <td style="font-weight: bold; font-size: 13px;">${{ number_format($factura->total, 2) }}</td>
        </tr>
        @if($fechaPago)
        <tr>
            <td class="label">Fecha Programada de Pago</td>
            <td style="font-weight: bold;">{{ \Carbon\Carbon::parse($fechaPago)->format('d/m/Y') }}</td>
        </tr>
        @endif
    </table>

    <p style="font-size: 10px; margin-top: 10px;">
        Se acusa recibo de la factura arriba mencionada. El pago se realizara en la fecha programada, sujeto a la correcta entrega de documentacion.
    </p>

    <div class="acuse">
        <div class="acuse-line">Sello de recibido</div>
    </div>

    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
    </div>
</body>
</html>
