<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Obras - Cobranza</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #000;
            line-height: 1.3;
            padding: 10px 20px;
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
            width: 120px;
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
            font-size: 11px;
            font-weight: bold;
            margin: 10px 0;
        }

        /* Data table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            border: 1px solid #000;
            padding: 3px 4px;
            font-size: 7px;
            font-weight: bold;
            background-color: #f0f0f0;
            text-align: center;
        }
        .data-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            font-size: 7px;
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

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #555;
            padding: 10px 20px;
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
                @if(file_exists(public_path('images/logo-steelex.png')))
                    <img src="{{ public_path('images/logo-steelex.png') }}" alt="Steelex">
                @else
                    <strong style="font-size: 16px; color: #1a5276;">STEELEX</strong><br>
                    <span style="font-size: 8px; color: #666;">ESTRUCTURAS METALICAS</span>
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">COBRANZA</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    REPORTE<br>OBRAS
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="title">REPORTE DE OBRAS - COBRANZA</div>

    @php
        $estadosFacturados = ['facturada', 'pago_parcial', 'pagado'];

        $totales = [
            'presupuestoPartidas' => 0,
            'presupuestoEjecutar' => 0,
            'totalDeducciones' => 0,
            'presupuestoFinal' => 0,
            'totalCobrado' => 0,
            'porCobrar' => 0,
            'estimacionesGeneradas' => 0,
            'estimacionesIngresadas' => 0,
            'facturadasPorCobrar' => 0,
            'totalFacturado' => 0,
        ];

        $datosObras = [];
        foreach ($obras as $obra) {
            $partidas = $obra->partidas ?? collect();
            $estimaciones = $obra->estimaciones ?? collect();
            $anticipos = $obra->anticipos ?? collect();
            $comparativos = $obra->comparativos ?? collect();
            $deducciones = $obra->deducciones ?? collect();

            $presupuestoPartidas = $partidas->where('es_adicional', false)->sum('monto');
            $partidasAdicionales = $partidas->where('es_adicional', true)->sum('monto');
            $basePartidas = $presupuestoPartidas + $partidasAdicionales;

            $lastComparativo = $comparativos
                ->whereIn('estado', ['implementado', 'aprobado'])
                ->sortByDesc('id')
                ->first();

            $lastComparativoCualquiera = $comparativos->sortByDesc('id')->first();
            $montoComparativoUltimo = $lastComparativoCualquiera ? (float) $lastComparativoCualquiera->monto_impacto : 0;

            $presupuestoEjecutar = $lastComparativoCualquiera ? $montoComparativoUltimo : $basePartidas;

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
            $totalDeduccionesObra = $deducciones->sum('monto');

            $ajustePresupuesto = $presupuestoEjecutar - $presupuestoPartidas;
            $presupuestoFinal = $presupuestoEjecutar - $totalDeduccionesObra;
            $porCobrar = $presupuestoFinal - $totalCobrado;

            $estimacionesGeneradas = $estimaciones->where('estado', 'generada')->sum('monto_estimado');
            $estimacionesIngresadas = $estimaciones->where('estado', 'ingresada')->sum('monto_estimado');
            $facturadasPorCobrar = $totalFacturado - $totalCobrado;

            $tieneComparativos = $lastComparativo !== null;
            $montoComparativo = $lastComparativo ? (float) $lastComparativo->monto_impacto : 0;

            $d = compact(
                'presupuestoPartidas', 'presupuestoEjecutar', 'ajustePresupuesto',
                'totalDeduccionesObra', 'presupuestoFinal', 'totalCobrado', 'porCobrar',
                'estimacionesGeneradas', 'estimacionesIngresadas', 'facturadasPorCobrar',
                'totalFacturado', 'tieneComparativos', 'montoComparativo',
            );
            $datosObras[] = ['obra' => $obra, 'datos' => $d];

            $totales['presupuestoPartidas'] += $presupuestoPartidas;
            $totales['presupuestoEjecutar'] += $presupuestoEjecutar;
            $totales['totalDeducciones'] += $totalDeduccionesObra;
            $totales['presupuestoFinal'] += $presupuestoFinal;
            $totales['totalCobrado'] += $totalCobrado;
            $totales['porCobrar'] += $porCobrar;
            $totales['estimacionesGeneradas'] += $estimacionesGeneradas;
            $totales['estimacionesIngresadas'] += $estimacionesIngresadas;
            $totales['facturadasPorCobrar'] += $facturadasPorCobrar;
            $totales['totalFacturado'] += $totalFacturado;
        }

        function pctPdf(float $valor, float $total): string {
            if ($total <= 0) return '0.00%';
            return number_format(($valor / $total) * 100, 2) . '%';
        }

        function moneyPdf(float $valor): string {
            return '$' . number_format($valor, 2);
        }
    @endphp

    {{-- Data Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>No</th>
                <th>Obra</th>
                <th>Presupuesto</th>
                <th>Pres. a ejecutar</th>
                <th>Comp. / Ajuste</th>
                <th>Deductivas</th>
                <th>Pres. final</th>
                <th>Cobrado</th>
                <th>Por cobrar</th>
                <th>% Cobrado</th>
                <th>Gen. por cobrar</th>
                <th>%</th>
                <th>Ing. por cobrar</th>
                <th>%</th>
                <th>Fact. por cobrar</th>
                <th>%</th>
                <th>Facturado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($datosObras as $item)
                @php $obra = $item['obra']; $d = $item['datos']; @endphp
                <tr>
                    <td>{{ $obra->cliente->nombre ?? '-' }}</td>
                    <td class="text-center">{{ $obra->no }}</td>
                    <td>{{ $obra->descripcion }}</td>
                    <td class="text-right">{{ moneyPdf($d['presupuestoPartidas']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['presupuestoEjecutar']) }}</td>
                    <td class="text-right">
                        @if($d['tieneComparativos'])
                            @if($obra->tipo_contrato === 'precio_unitario')
                                {{ moneyPdf($d['ajustePresupuesto']) }}
                            @else
                                {{ moneyPdf($d['montoComparativo']) }}
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ moneyPdf($d['totalDeduccionesObra']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['presupuestoFinal']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['totalCobrado']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['porCobrar']) }}</td>
                    <td class="text-right">{{ pctPdf($d['totalCobrado'], $d['presupuestoFinal']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['estimacionesGeneradas']) }}</td>
                    <td class="text-right">{{ pctPdf($d['estimacionesGeneradas'], $d['presupuestoEjecutar']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['estimacionesIngresadas']) }}</td>
                    <td class="text-right">{{ pctPdf($d['estimacionesIngresadas'], $d['presupuestoEjecutar']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['facturadasPorCobrar']) }}</td>
                    <td class="text-right">{{ pctPdf($d['facturadasPorCobrar'], $d['presupuestoEjecutar']) }}</td>
                    <td class="text-right">{{ moneyPdf($d['totalFacturado']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="18" class="text-center">No hay obras registradas</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($datosObras) > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTALES:</td>
                    <td class="text-right">{{ moneyPdf($totales['presupuestoPartidas']) }}</td>
                    <td class="text-right">{{ moneyPdf($totales['presupuestoEjecutar']) }}</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ moneyPdf($totales['totalDeducciones']) }}</td>
                    <td class="text-right">{{ moneyPdf($totales['presupuestoFinal']) }}</td>
                    <td class="text-right">{{ moneyPdf($totales['totalCobrado']) }}</td>
                    <td class="text-right">{{ moneyPdf($totales['porCobrar']) }}</td>
                    <td class="text-right">{{ pctPdf($totales['totalCobrado'], $totales['presupuestoFinal']) }}</td>
                    <td class="text-right">{{ moneyPdf($totales['estimacionesGeneradas']) }}</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ moneyPdf($totales['estimacionesIngresadas']) }}</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ moneyPdf($totales['facturadasPorCobrar']) }}</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ moneyPdf($totales['totalFacturado']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Footer --}}
    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">Generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
