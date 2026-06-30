<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra {{ $oc->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; line-height: 1.4; padding: 10px 60px 130px; }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 180px; }
        .logo-cell img { max-width: 170px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-address { font-size: 8.5px; line-height: 1.35; margin-top: 3px; }
        .code-cell { width: 140px; text-align: center; vertical-align: top; }
        .code-title { font-size: 11px; font-weight: bold; margin-bottom: 5px; }
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
        .resumen-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .tot-cell { width: 35%; padding: 0; border: 1px solid #000; vertical-align: top; }
        .tot-inner { width: 100%; border-collapse: collapse; }
        .tot-inner td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #ccc; }
        .tot-inner .tot-label { font-weight: bold; background-color: #f0f0f0; }
        .tot-inner .tot-value { text-align: right; }
        .tot-inner .tot-final td { font-weight: bold; font-size: 11px; border-bottom: none; background-color: #f0f0f0; }
        .resumen-table .letras-cell { border: 1px solid #000; padding: 5px 10px; font-size: 8.5px; font-style: italic; text-transform: uppercase; }
        .condiciones-fija { position: fixed; bottom: 15px; left: 60px; right: 60px; border: 1px solid #000; padding: 8px 10px; font-size: 9px; }
        .condiciones-fija .cond-title { font-weight: bold; font-size: 10px; margin-bottom: 5px; text-transform: uppercase; }
        .condiciones-fija ol { margin: 0 0 0 16px; padding: 0; }
        .condiciones-fija li { margin-bottom: 4px; line-height: 1.35; }
    </style>
</head>
<body>
    @php
        $fecha = $oc->created_at ?? now();
    @endphp

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
                <div class="code-title">ORDEN DE COMPRA</div>
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
        <tr>
            <td class="label">Requisición</td>
            <td colspan="3">{{ $oc->requisicion?->folio ?? '-' }}</td>
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
                <th>OP</th>
                <th>Uso CFDI</th>
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
                <td>{{ $d->obraRubro?->obra?->no ? 'OP-'.$d->obraRubro->obra->no : '-' }}</td>
                <td>{{ optional($d->usoCfdi)->clave ?? '-' }}</td>
                <td>{{ $d->unidad }}</td>
                <td class="text-right">{{ number_format($d->cantidad, 2) }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->cantidad * $d->precio_unitario, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($oc->notas)
    <div class="notas">
        <strong>Notas:</strong> {{ $oc->notas }}
    </div>
    @endif

    <table class="resumen-table">
        <tr>
            <td style="width: 65%; border: none;"></td>
            <td class="tot-cell">
                <table class="tot-inner">
                    <tr>
                        <td class="tot-label">SUBTOTAL</td>
                        <td class="tot-value">${{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-label">IVA (16%)</td>
                        <td class="tot-value">${{ number_format($iva, 2) }}</td>
                    </tr>
                    <tr class="tot-final">
                        <td class="tot-label">TOTAL</td>
                        <td class="tot-value">${{ number_format($oc->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="letras-cell">{{ \App\Support\NumeroALetras::convertir((float) $oc->total, $oc->moneda ?? 'mxn') }}</td>
        </tr>
    </table>

    {{-- Condiciones fijas al final de la hoja --}}
    <div class="condiciones-fija">
        <div class="cond-title">Condiciones</div>
        <ol>
            <li>EL MATERIAL SE ENTREGA CON CERTIFICADO DE CALIDAD.</li>
            <li>SE REQUIERE INVARIABLEMENTE LA FACTURA ORIGINAL Y DOS COPIAS PARA SU RECEPCIÓN EN EL HORARIO ESTABLECIDO DE 8:00 A 13:00 Y DE 14:00 A 16:00 HORAS.</li>
            <li>LA FACTURA ELECTRÓNICA DEBE SUBIRSE AL PORTAL STX.STEELEX.COM.MX/PORTAL EN ASOCIACIÓN A SU O.C. UNA VEZ EMITIDA.</li>
        </ol>
    </div>
</body>
</html>
