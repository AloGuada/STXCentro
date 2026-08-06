<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción {{ $entrega->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
            padding: 10px 40px;
        }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 180px; }
        .logo-cell img { max-width: 170px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-address { font-size: 8.5px; line-height: 1.35; margin-top: 3px; }
        .code-cell { width: 150px; text-align: center; vertical-align: top; }
        .code-title { font-size: 13px; font-weight: bold; margin-bottom: 6px; border-bottom: 2px solid #000; padding-bottom: 3px; }
        .code-box { font-size: 9.5px; text-align: left; }
        .code-box .label { font-weight: bold; }

        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 12px 0; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 22%; }

        .detalles-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .detalles-table th {
            border: 1px solid #000; padding: 4px 8px; font-size: 10px;
            font-weight: bold; background-color: #f0f0f0; text-align: center;
        }
        .detalles-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .detalles-table .text-right { text-align: right; }
        .detalles-table .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .detalles-table .pago-row td { background-color: #e0e0e0; font-size: 11px; }
        .detalles-table .letras-row td { font-size: 9px; font-style: italic; text-transform: uppercase; }

        .conditions {
            position: fixed;
            bottom: 16px;
            left: 40px;
            right: 40px;
            font-size: 8px;
            line-height: 1.4;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
        .conditions .cond-title { font-weight: bold; font-size: 9.5px; margin-bottom: 3px; }
        .conditions ol { margin-left: 16px; }
        .conditions li { margin-bottom: 2px; text-align: justify; }
    </style>
</head>
<body>
    @php
        $oc = $entrega->ordenCompra;
        $proveedor = $oc?->proveedor;
        $obra = $oc?->obra;
        $folio = $entrega->folio ?? ('REC-'.str_pad((string) $entrega->id, 4, '0', STR_PAD_LEFT));
        $total = 0.0;
        foreach ($entrega->detalles as $d) {
            $total += (float) $d->cantidad_recibida * (float) $d->precio_unitario_efectivo;
        }
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
                <div class="code-title">RECEPCIÓN</div>
                <div class="code-box">
                    <span class="label">F O L I O:</span> {{ $folio }}<br>
                    <span class="label">Fecha:</span> {{ $entrega->fecha_entrega?->format('d/m/Y') ?? '-' }}<br>
                    <span class="label">Orden de compra:</span> {{ $oc?->folio ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="title">FORMATO DE RECEPCIÓN</div>

    {{-- Info --}}
    <table class="info-table">
        <tr>
            <td class="label">Folio</td>
            <td>{{ $folio }}</td>
            <td class="label">Tipo</td>
            <td style="text-transform: capitalize;">{{ $entrega->tipo }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td colspan="3"><strong>{{ $proveedor?->razon_social ?? 'Sin proveedor' }}</strong></td>
        </tr>
        @if($proveedor?->domicilio_fiscal)
        <tr>
            <td class="label">Dirección</td>
            <td colspan="3">{{ $proveedor->domicilio_fiscal }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Asignación (obra)</td>
            <td>{{ $obra ? trim(($obra->no ? $obra->no.' - ' : '').$obra->descripcion) : '-' }}</td>
            <td class="label">Factura</td>
            <td>{{ $entrega->factura?->folio_fiscal ?? $entrega->factura?->folio ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Recibió</td>
            <td>{{ $entrega->recibidor?->name ?? '-' }}</td>
            <td class="label">Fecha de recepción</td>
            <td>{{ $entrega->fecha_entrega?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        @if($entrega->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $entrega->observaciones }}</td>
        </tr>
        @endif
    </table>

    {{-- Detalles recibidos --}}
    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 12%;">Cantidad</th>
                <th style="width: 15%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 15%;">Costo Unitario</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entrega->detalles as $d)
            @php $ocd = $d->ordenCompraDetalle; $importe = (float) $d->cantidad_recibida * (float) $d->precio_unitario_efectivo; @endphp
            <tr>
                <td class="text-right">{{ number_format($d->cantidad_recibida, 2) }} {{ $ocd?->unidad }}</td>
                <td>{{ $ocd?->codigo_producto ?? '-' }}</td>
                <td>{{ $ocd?->descripcion ?? '-' }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario_efectivo, 2) }}</td>
                <td class="text-right">${{ number_format($importe, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $codMon = ($moneda ?? 'mxn') === 'mxn' ? '' : ' '.strtoupper($moneda);
                $factura = $entrega->factura;
                $monFac = $factura?->moneda ?? ($moneda ?? 'mxn');
                $codFac = $monFac === 'mxn' ? '' : ' '.strtoupper($monFac);
                $totalPagar = $factura ? (float) $factura->total : $total;
                $monPagar = $factura ? $monFac : ($moneda ?? 'mxn');
            @endphp
            <tr class="total-row">
                <td colspan="4" class="text-right">SUBTOTAL (RECIBIDO)</td>
                <td class="text-right">${{ number_format($total, 2) }}{{ $codMon }}</td>
            </tr>
            @if($factura)
            <tr class="total-row">
                <td colspan="4" class="text-right">IVA / IMPUESTOS</td>
                <td class="text-right">${{ number_format((float) $factura->iva, 2) }}{{ $codFac }}</td>
            </tr>
            @endif
            <tr class="total-row pago-row">
                <td colspan="4" class="text-right">TOTAL DE LA FACTURA{{ $factura ? ' ('.($factura->folio_fiscal ?? $factura->folio).')' : '' }}</td>
                <td class="text-right">${{ number_format($totalPagar, 2) }}{{ $factura ? $codFac : $codMon }}</td>
            </tr>
            <tr class="letras-row">
                <td colspan="5">{{ \App\Support\NumeroALetras::convertir($totalPagar, $monPagar) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Condiciones (pie de página) --}}
    <div class="conditions">
        <div class="cond-title">Condiciones</div>
        <ol>
            <li>EL MATERIAL SE ENTREGA CON CERTIFICADO DE CALIDAD</li>
            <li>SE REQUIERE INVARIABLEMENTE LA FACTURA ORIGINAL Y DOS COPIAS PARA SU RECEPCIÓN EN EL HORARIO ESTABLECIDO DE 8:00 A 13:00 Y DE 14:00 A 16:00 HORAS</li>
            <li>LA FACTURA ELECTRÓNICA DEBE SUBIRSE AL PORTAL STX.STEELEX.COM.MX/PORTAL EN ASOCIACIÓN A SU O.C. UNA VEZ EMITIDA</li>
        </ol>
    </div>
</body>
</html>
