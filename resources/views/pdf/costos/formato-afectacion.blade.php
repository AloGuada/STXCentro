<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>F-STX-COSTOS-02 Afectacion Presupuestal</title>
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
        .info-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 25%; }
        .detalles-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalles-table th { border: 1px solid #000; padding: 4px 8px; font-size: 10px; font-weight: bold; background-color: #f0f0f0; text-align: center; }
        .detalles-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .detalles-table .text-right { text-align: right; }
        .detalles-table .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .signatures-table { width: 100%; margin-top: 50px; }
        .signatures-table td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 15px; }
        .sig-name { font-weight: bold; font-size: 10px; border-top: 1px solid #000; padding-top: 5px; margin-top: 50px; }
        .sig-role { font-size: 9px; color: #555; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #555; padding: 10px 40px; }
        .footer .footer-code { text-align: right; font-size: 9px; margin-top: 3px; }
    </style>
</head>
<body>
    @php
        $fecha = $afectacion->fecha ?? now();
    @endphp

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
                <div class="company-dept">COSTOS</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    F-STX-COSTOS-02<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="title">AFECTACION PRESUPUESTAL</div>

    <table class="info-table">
        <tr>
            <td class="label">Folio</td>
            <td>{{ $afectacion->folio }}</td>
            <td class="label">Fecha</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $afectacion->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Creado por</td>
            <td>{{ $afectacion->creadoPor?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td colspan="3">{{ $afectacion->proveedor?->razon_social ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Tipo Origen</td>
            <td colspan="3" style="text-transform: capitalize;">{{ str_replace('_', ' ', $afectacion->tipo_origen) }}</td>
        </tr>
        <tr>
            <td class="label">Descripcion</td>
            <td colspan="3">{{ $afectacion->descripcion }}</td>
        </tr>
    </table>

    @if($afectacion->detalles && $afectacion->detalles->count() > 0)
    <table class="detalles-table">
        <thead>
            <tr>
                <th>Centro de Costos</th>
                <th>Concepto</th>
                <th>Cantidad</th>
                <th>P. Unitario</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($afectacion->detalles as $detalle)
            <tr>
                <td>{{ $detalle->obraRubro?->rubro?->codigo ?? '-' }}</td>
                <td>{{ $detalle->concepto }}</td>
                <td class="text-right">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($detalle->monto, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right">${{ number_format($afectacion->monto_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-name">{{ $afectacion->creadoPor?->name ?? '________________' }}</div>
                <div class="sig-role">Elaboro</div>
            </td>
            <td>
                <div class="sig-name">________________</div>
                <div class="sig-role">Reviso</div>
            </td>
            <td>
                <div class="sig-name">________________</div>
                <div class="sig-role">Autorizo</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">F-STX-COSTOS-02<br>Revision: 00</div>
    </div>
</body>
</html>
