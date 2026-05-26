<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra {{ $oc->folio }}</title>
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
        .notas { margin-top: 15px; padding: 8px; border: 1px solid #ccc; background: #fafafa; font-size: 10px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #555; padding: 10px 40px; }
    </style>
</head>
<body>
    @php
        $fecha = $oc->created_at ?? now();
    @endphp

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
                    F-STX-COSTOS-OC<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="title">ORDEN DE COMPRA</div>

    <table class="info-table">
        <tr>
            <td class="label">Folio</td>
            <td>{{ $oc->folio }}</td>
            <td class="label">Fecha</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td>{{ $oc->proveedor?->razon_social ?? '-' }}</td>
            <td class="label">RFC</td>
            <td>{{ $oc->proveedor?->rfc ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $oc->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Moneda</td>
            <td>{{ strtoupper($oc->moneda ?? 'mxn') }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de Pago</td>
            <td style="text-transform: capitalize;">{{ $oc->tipo_pago }}</td>
            <td class="label">Entrega Esperada</td>
            <td>{{ $oc->fecha_entrega_esperada ? \Carbon\Carbon::parse($oc->fecha_entrega_esperada)->format('d/m/Y') : '-' }}</td>
        </tr>
    </table>

    @php
        $subtotal = $oc->detalles->sum(fn($d) => (float) $d->cantidad * (float) $d->precio_unitario);
        $iva = $subtotal * 0.16;
    @endphp

    <table class="detalles-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Descripcion</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>P. Unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($oc->detalles as $i => $d)
            <tr>
                <td class="text-right">{{ $i + 1 }}</td>
                <td>{{ $d->descripcion }}</td>
                <td>{{ $d->unidad }}</td>
                <td class="text-right">{{ number_format($d->cantidad, 2) }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->cantidad * $d->precio_unitario, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">SUBTOTAL</td>
                <td class="text-right">${{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="5" class="text-right">IVA (16%)</td>
                <td class="text-right">${{ number_format($iva, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                <td class="text-right">${{ number_format($oc->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($oc->notas)
    <div class="notas">
        <strong>Notas:</strong> {{ $oc->notas }}
    </div>
    @endif

    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
    </div>
</body>
</html>
