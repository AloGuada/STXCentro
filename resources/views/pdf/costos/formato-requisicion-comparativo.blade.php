<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comparativo {{ $requisicion->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; line-height: 1.4; padding: 10px 40px; }
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
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info-table td { border: 1px solid #000; padding: 3px 6px; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 20%; }
        .comp-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .comp-table th, .comp-table td { border: 1px solid #000; padding: 3px 5px; font-size: 9px; }
        .comp-table th { font-weight: bold; background-color: #f0f0f0; text-align: center; }
        .comp-table .text-right { text-align: right; }
        .comp-table .mejor { background-color: #d4edda; font-weight: bold; }
        .comp-table .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .signatures-table { width: 100%; margin-top: 40px; }
        .signatures-table td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-placeholder { height: 50px; }
        .sig-name { font-weight: bold; font-size: 10px; border-top: 1px solid #000; padding-top: 5px; }
        .sig-role { font-size: 9px; color: #555; }
        .sig-date { font-size: 8px; color: #888; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #555; padding: 10px 40px; }
    </style>
</head>
<body>
    @php
        $fecha = $requisicion->created_at ?? now();

        // Build proveedores map from cotizaciones
        $proveedores = collect();
        foreach ($requisicion->detalles as $d) {
            foreach ($d->cotizaciones as $c) {
                if ($c->proveedor && !$proveedores->has($c->proveedor_id)) {
                    $proveedores->put($c->proveedor_id, $c->proveedor);
                }
            }
        }
        $provList = $proveedores->values();

        // Calc totals per proveedor
        $totalesProv = [];
        $totalPartidas = $requisicion->detalles->count();
        foreach ($provList as $p) {
            $total = 0;
            $partidasCotizadas = 0;
            foreach ($requisicion->detalles as $d) {
                $cot = $d->cotizaciones->firstWhere('proveedor_id', $p->id);
                if ($cot) {
                    $total += (float) $cot->precio_unitario * (float) $d->cantidad;
                    $partidasCotizadas++;
                }
            }
            $totalesProv[$p->id] = [
                'total' => $total,
                'completo' => $partidasCotizadas === $totalPartidas,
            ];
        }

        // Find mejor proveedor (cotizo todas las partidas, menor total)
        $mejorId = null;
        $mejorTotal = PHP_FLOAT_MAX;
        foreach ($totalesProv as $pid => $info) {
            if ($info['completo'] && $info['total'] < $mejorTotal) {
                $mejorTotal = $info['total'];
                $mejorId = $pid;
            }
        }

        // Find mejor precio por partida
        $mejorPrecioPartida = [];
        foreach ($requisicion->detalles as $d) {
            $precios = $d->cotizaciones->pluck('precio_unitario')->map(fn($p) => (float) $p)->filter(fn($p) => $p > 0);
            if ($precios->isNotEmpty()) {
                $mejorPrecioPartida[$d->id] = $precios->min();
            }
        }
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
                    F-STX-COSTOS-CMP<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="title">COMPARATIVO DE COTIZACIONES</div>

    <table class="info-table">
        <tr>
            <td class="label">Folio Requisicion</td>
            <td>{{ $requisicion->folio }}</td>
            <td class="label">Fecha</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $requisicion->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Solicitante</td>
            <td>{{ $requisicion->solicitante?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="comp-table">
        <thead>
            <tr>
                <th>Cantidad</th>
                <th>Descripcion</th>
                @foreach($provList as $p)
                <th>{{ $p->nombre_comercial ?: $p->razon_social }}@if($p->id === $mejorId) *@endif</th>
                @endforeach
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisicion->detalles as $d)
            @php
                $mejorPrecio = $mejorPrecioPartida[$d->id] ?? null;
                $importePrecio = null;
                if ($mejorId) {
                    $cot = $d->cotizaciones->firstWhere('proveedor_id', $mejorId);
                    $importePrecio = $cot ? (float) $cot->precio_unitario : null;
                }
                if ($importePrecio === null && $mejorPrecio !== null) {
                    $importePrecio = $mejorPrecio;
                }
                $importe = $importePrecio !== null ? $importePrecio * (float) $d->cantidad : null;
            @endphp
            <tr>
                <td class="text-right">{{ number_format($d->cantidad, 2) }} {{ $d->unidad }}</td>
                <td>{{ $d->descripcion }}</td>
                @foreach($provList as $p)
                @php
                    $cot = $d->cotizaciones->firstWhere('proveedor_id', $p->id);
                    $precio = $cot ? (float) $cot->precio_unitario : null;
                    $esMejor = $precio !== null && $mejorPrecio !== null && abs($precio - $mejorPrecio) < 0.01;
                @endphp
                <td class="text-right {{ $esMejor ? 'mejor' : '' }}">
                    @if($precio !== null)
                        ${{ number_format($precio, 2) }}
                        @if($cot->tiempo_entrega_dias)
                            <br><span style="font-size: 8px; color: #666;">{{ $cot->tiempo_entrega_dias }}d</span>
                        @endif
                    @else
                        -
                    @endif
                </td>
                @endforeach
                <td class="text-right">
                    @if($importe !== null)
                        ${{ number_format($importe, 2) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        @php
            $subtotalComp = 0;
            foreach ($requisicion->detalles as $d) {
                $importePrecio = null;
                if ($mejorId) {
                    $cot = $d->cotizaciones->firstWhere('proveedor_id', $mejorId);
                    $importePrecio = $cot ? (float) $cot->precio_unitario : null;
                }
                if ($importePrecio === null) {
                    $importePrecio = $mejorPrecioPartida[$d->id] ?? 0;
                }
                $subtotalComp += $importePrecio * (float) $d->cantidad;
            }
            $ivaComp = $subtotalComp * 0.16;
            $totalComp = $subtotalComp + $ivaComp;
        @endphp
        <tfoot>
            <tr class="total-row">
                <td colspan="{{ 2 + $provList->count() }}" class="text-right">SUBTOTAL</td>
                <td class="text-right">${{ number_format($subtotalComp, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="{{ 2 + $provList->count() }}" class="text-right">IVA (16%)</td>
                <td class="text-right">${{ number_format($ivaComp, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="{{ 2 + $provList->count() }}" class="text-right">TOTAL</td>
                <td class="text-right">${{ number_format($totalComp, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($mejorId)
    <p style="font-size: 9px; color: #555; margin-bottom: 15px;">* Proveedor seleccionado: <strong>{{ $proveedores->get($mejorId)?->razon_social }}</strong> con total de ${{ number_format($mejorTotal, 2) }}</p>
    @endif

    @if($firmas->isNotEmpty())
    <table class="signatures-table">
        <tr>
            @foreach($firmas as $firma)
            <td>
                <div class="sig-placeholder"></div>
                <div class="sig-name">{{ $firma->aprobada ? $firma->aprobador?->name : 'Pendiente' }}</div>
                <div class="sig-role">{{ $firma->permiso->descripcion }} (Nivel {{ $firma->permiso->nivel }})</div>
                @if($firma->aprobada && $firma->fecha)
                <div class="sig-date">{{ $firma->fecha }}</div>
                @endif
            </td>
            @endforeach
        </tr>
    </table>
    @endif

    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
    </div>
</body>
</html>
