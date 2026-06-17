<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta - {{ $obra->no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            line-height: 1.3;
            padding: 10px 30px;
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
            width: 150px;
        }
        .logo-cell img {
            max-width: 140px;
        }
        .company-cell {
            text-align: center;
            vertical-align: middle;
        }
        .company-name {
            font-size: 12px;
            font-weight: bold;
        }
        .company-url {
            font-size: 9px;
            color: #0563C1;
        }
        .company-dept {
            font-size: 9px;
            font-weight: bold;
        }
        .code-cell {
            width: 130px;
            text-align: center;
            vertical-align: top;
        }
        .code-box {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }

        /* Title */
        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 12px 0;
        }

        /* Summary tables side by side */
        .summary-wrapper {
            width: 100%;
            margin-bottom: 15px;
        }
        .summary-wrapper td {
            vertical-align: top;
            width: 50%;
        }
        .summary-table {
            width: 95%;
            border-collapse: collapse;
        }
        .summary-table th {
            border: 1px solid #000;
            padding: 3px 6px;
            font-size: 9px;
            font-weight: bold;
            background-color: #f0f0f0;
            text-align: center;
            colspan: 2;
        }
        .summary-table td {
            border: 1px solid #000;
            padding: 3px 6px;
            font-size: 9px;
        }
        .summary-table .label {
            font-weight: bold;
            background-color: #f9f9f9;
            width: 50%;
        }
        .summary-table .value {
            text-align: right;
            width: 50%;
        }

        /* Estimaciones table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 8px;
            font-weight: bold;
            background-color: #f0f0f0;
            text-align: center;
        }
        .data-table td {
            border: 1px solid #000;
            padding: 2px 5px;
            font-size: 8px;
        }
        .data-table .text-right {
            text-align: right;
        }
        .data-table .text-center {
            text-align: center;
        }
        .data-table .total-row td {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .data-table .group-header th {
            background-color: #e0e0e0;
            font-size: 9px;
        }

        /* Total por cobrar */
        .total-cobrar {
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            margin: 10px 0;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #555;
            padding: 10px 30px;
        }
        .footer .footer-code {
            text-align: right;
            font-size: 8px;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo', ['width' => 140])
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">COBRANZA</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    ESTADO DE CUENTA<br>CLIENTES
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="title">ESTADO DE CUENTA CLIENTES</div>

    @php
        $estadosFacturados = ['facturada', 'pago_parcial', 'pagado'];

        $partidas = $obra->partidas ?? collect();
        $estimaciones = $obra->estimaciones ?? collect();
        $anticipos = $obra->anticipos ?? collect();
        $comparativos = $obra->comparativos ?? collect();
        $deducciones = $obra->deducciones ?? collect();

        $presupuestoPartidas = $partidas->sum('monto');
        $basePartidas = $presupuestoPartidas;

        $lastComparativo = $comparativos
            ->whereIn('estado', ['implementado', 'aprobado'])
            ->sortByDesc('id')
            ->first();

        $lastComparativoCualquiera = $comparativos->sortByDesc('id')->first();
        $montoComparativoUltimo = $lastComparativoCualquiera ? (float) $lastComparativoCualquiera->monto_impacto : 0;

        $presupuestoEjecutar = $lastComparativoCualquiera ? $montoComparativoUltimo : $basePartidas;

        $ajusteIngenieria = $presupuestoEjecutar - $presupuestoPartidas;

        $totalAnticiposFacturados = $anticipos->sum('monto');
        $totalAnticiposCobrados = $anticipos->whereNotNull('fecha_pagado')->sum('monto');

        $totalEstimacionesFacturadas = $estimaciones
            ->whereIn('estado', $estadosFacturados)
            ->sum('monto_estimado');

        $totalEstimacionesCobradas = $estimaciones
            ->where('estado', 'pagado')
            ->sum('monto_estimado')
            + $estimaciones
                ->where('estado', 'pago_parcial')
                ->sum(fn ($e) => $e->pagos ? $e->pagos->sum('monto_pagado') : 0);

        $totalFacturado = $totalAnticiposFacturados + $totalEstimacionesFacturadas;
        $totalCobrado = $totalAnticiposCobrados + $totalEstimacionesCobradas;
        $totalDeducciones = $deducciones->sum('monto');
        $presupuestoFinal = $presupuestoEjecutar - $totalDeducciones;
        $porFacturar = $presupuestoFinal - $totalFacturado;
        $porCobrar = $presupuestoFinal - $totalCobrado;

        $anticipoPct = (float) ($obra->anticipo ?? 0);
        $anticipoMonto = $presupuestoEjecutar * ($anticipoPct / 100);

        $pendientePorEstimar = $presupuestoEjecutar - $estimaciones->sum('monto_estimado') - $totalAnticiposFacturados;

        function moneyEc(float $valor): string {
            return '$' . number_format($valor, 2);
        }

        function fechaEc(?string $fecha): string {
            if (!$fecha) return '-';
            return \Carbon\Carbon::parse($fecha)->format('d/m/y');
        }
    @endphp

    {{-- Summary: two side-by-side tables --}}
    <table class="summary-wrapper">
        <tr>
            {{-- Left: Datos del Proyecto --}}
            <td>
                <table class="summary-table">
                    <thead>
                        <tr><th colspan="2">DATOS DEL PROYECTO</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label">OBRA</td>
                            <td class="value" style="text-align: left;">{{ $obra->descripcion }}</td>
                        </tr>
                        <tr>
                            <td class="label">CLIENTE</td>
                            <td class="value" style="text-align: left;">{{ $obra->cliente->nombre ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">NUMERO</td>
                            <td class="value">{{ $obra->no }}</td>
                        </tr>
                        <tr>
                            <td class="label">PRESUPUESTO</td>
                            <td class="value">{{ moneyEc($presupuestoPartidas) }}</td>
                        </tr>
                        <tr>
                            <td class="label">IMP. INGENIERIA</td>
                            <td class="value">{{ moneyEc($ajusteIngenieria) }}</td>
                        </tr>
                        <tr>
                            <td class="label">DEDUCTIVA</td>
                            <td class="value">{{ moneyEc($totalDeducciones) }}</td>
                        </tr>
                        <tr>
                            <td class="label">FACTURADO</td>
                            <td class="value">{{ moneyEc($totalFacturado) }}</td>
                        </tr>
                        <tr>
                            <td class="label">POR FACTURAR</td>
                            <td class="value">{{ moneyEc($porFacturar) }}</td>
                        </tr>
                        <tr>
                            <td class="label">PENDIENTE POR ESTIMAR</td>
                            <td class="value">{{ moneyEc($pendientePorEstimar) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Right: Resumen Financiero --}}
            <td>
                <table class="summary-table" style="float: right;">
                    <thead>
                        <tr><th colspan="2">RESUMEN FINANCIERO</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label">PRESUPUESTO</td>
                            <td class="value">{{ moneyEc($presupuestoEjecutar) }}</td>
                        </tr>
                        <tr>
                            <td class="label">IMP. INGENIERIA</td>
                            <td class="value">{{ moneyEc($ajusteIngenieria) }}</td>
                        </tr>
                        <tr>
                            <td class="label">DEDUCTIVA</td>
                            <td class="value">{{ moneyEc($totalDeducciones) }}</td>
                        </tr>
                        <tr>
                            <td class="label">FACTURADO</td>
                            <td class="value">{{ moneyEc($totalFacturado) }}</td>
                        </tr>
                        <tr>
                            <td class="label">COBRADO</td>
                            <td class="value">{{ moneyEc($totalCobrado) }}</td>
                        </tr>
                        <tr>
                            <td class="label">POR FACTURAR</td>
                            <td class="value">{{ moneyEc($porFacturar) }}</td>
                        </tr>
                        <tr>
                            <td class="label">POR COBRAR</td>
                            <td class="value">{{ moneyEc($porCobrar) }}</td>
                        </tr>
                        <tr>
                            <td class="label">ANTICIPO ({{ number_format($anticipoPct, 2) }}%)</td>
                            <td class="value">{{ moneyEc($anticipoMonto) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- Estimaciones table with grouped columns --}}
    <table class="data-table">
        <thead>
            <tr class="group-header">
                <th colspan="4">ESTIMACIONES</th>
                <th colspan="3">FACTURACION</th>
                <th colspan="2">COBRANZA</th>
            </tr>
            <tr>
                <th>No</th>
                <th>TIPO</th>
                <th>FECHA</th>
                <th>MONTO</th>
                <th>FECHA</th>
                <th>FOLIO</th>
                <th>IMPORTE</th>
                <th>FECHA</th>
                <th>IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalEstMonto = 0;
                $totalFactImporte = 0;
                $totalCobImporte = 0;

                $sortedEstimaciones = $estimaciones->sortBy('numero_estimacion');
            @endphp

            @forelse($sortedEstimaciones as $est)
                @php
                    $esFacturada = in_array($est->estado, $estadosFacturados);
                    $esCobrada = $est->estado === 'pagado';
                    $montoPagado = $est->estado === 'pago_parcial'
                        ? ($est->pagos ? $est->pagos->sum('monto_pagado') : 0)
                        : ($esCobrada ? (float) $est->monto_estimado : 0);

                    // Fecha de facturacion: buscar en historial el cambio a estado facturada
                    $fechaFactura = null;
                    $fechaCobro = null;
                    if ($est->historial) {
                        $histFactura = $est->historial->whereIn('estado_nuevo', $estadosFacturados)->sortBy('fecha_cambio')->first();
                        $fechaFactura = $histFactura?->fecha_cambio;

                        $histCobro = $est->historial->whereIn('estado_nuevo', ['pagado', 'pago_parcial'])->sortBy('fecha_cambio')->first();
                        $fechaCobro = $histCobro?->fecha_cambio;
                    }

                    // Ultimo pago fecha
                    if (!$fechaCobro && $est->pagos && $est->pagos->count() > 0) {
                        $fechaCobro = $est->pagos->sortByDesc('fecha_pago')->first()?->fecha_pago;
                    }

                    $totalEstMonto += (float) $est->monto_estimado;
                    $totalFactImporte += $esFacturada ? (float) $est->monto_estimado : 0;
                    $totalCobImporte += $montoPagado;
                @endphp
                <tr>
                    <td class="text-center">{{ $est->numero_estimacion }}</td>
                    <td class="text-center">{{ ucfirst($est->tipo ?? '-') }}</td>
                    <td class="text-center">{{ $est->inicio ? fechaEc($est->inicio) : '-' }}</td>
                    <td class="text-right">{{ moneyEc($est->monto_estimado) }}</td>
                    <td class="text-center">{{ $esFacturada ? fechaEc($fechaFactura) : '-' }}</td>
                    <td class="text-center">{{ $est->folio ?? '-' }}</td>
                    <td class="text-right">{{ $esFacturada ? moneyEc($est->monto_estimado) : '-' }}</td>
                    <td class="text-center">{{ $montoPagado > 0 ? fechaEc($fechaCobro) : '-' }}</td>
                    <td class="text-right">{{ $montoPagado > 0 ? moneyEc($montoPagado) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">No hay estimaciones registradas</td>
                </tr>
            @endforelse
        </tbody>
        @if($estimaciones->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL</td>
                    <td class="text-right">{{ moneyEc($totalEstMonto) }}</td>
                    <td colspan="2"></td>
                    <td class="text-right">{{ moneyEc($totalFactImporte) }}</td>
                    <td></td>
                    <td class="text-right">{{ moneyEc($totalCobImporte) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Anticipos table --}}
    @if($anticipos->count() > 0)
        <table class="data-table" style="margin-top: 10px;">
            <thead>
                <tr class="group-header">
                    <th colspan="5">ANTICIPOS</th>
                </tr>
                <tr>
                    <th>FOLIO</th>
                    <th>FECHA EMISION</th>
                    <th>MONTO</th>
                    <th>ESTADO</th>
                    <th>FECHA PAGADO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($anticipos as $ant)
                    <tr>
                        <td class="text-center">{{ $ant->folio ?? '-' }}</td>
                        <td class="text-center">{{ fechaEc($ant->fecha_emision) }}</td>
                        <td class="text-right">{{ moneyEc($ant->monto) }}</td>
                        <td class="text-center">{{ ucfirst($ant->estado) }}</td>
                        <td class="text-center">{{ fechaEc($ant->fecha_pagado) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-right">TOTAL</td>
                    <td class="text-right">{{ moneyEc($anticipos->sum('monto')) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Total por cobrar --}}
    <div class="total-cobrar">
        TOTAL POR COBRAR: {{ moneyEc($porCobrar) }}
    </div>

    {{-- Footer --}}
    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">Generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
