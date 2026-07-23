<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Semanal de Facturas - Semana {{ $semana }}</title>
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
        .company-url {
            font-size: 10px;
            color: #0563C1;
        }
        .company-dept {
            font-size: 10px;
            font-weight: bold;
        }
        .code-cell {
            width: 120px;
            text-align: center;
            vertical-align: top;
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
        .detalles-table .text-center {
            text-align: center;
        }
        .detalles-table .total-row td {
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
    {{-- Header --}}
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
                    REPORTE SEMANAL<br>FACTURAS
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="title">REPORTE SEMANAL DE FACTURAS</div>

    {{-- Info --}}
    <table class="info-table">
        <tr>
            <td class="label">Semana</td>
            <td>{{ $semana }}</td>
            <td class="label">Año</td>
            <td>{{ $anio }}</td>
        </tr>
        <tr>
            <td class="label">Periodo</td>
            <td colspan="3">{{ $fechaInicio }} - {{ $fechaFin }}</td>
        </tr>
        @php
            // Suma por divisa: "$X + $Y USD" cuando el conjunto mezcla monedas.
            $sumaPorMoneda = fn ($col) => $col
                ->groupBy(fn ($f) => strtolower($f->moneda ?? 'mxn'))
                ->map(fn ($fs) => $fs->sum('total'))
                ->sortKeys()
                ->map(fn ($t, $m) => '$'.number_format($t, 2).($m === 'mxn' ? '' : ' '.strtoupper($m)))
                ->implode(' + ');
        @endphp
        <tr>
            <td class="label">Total Facturas</td>
            <td>{{ $facturas->count() }}</td>
            <td class="label">Suma Total</td>
            <td>{{ $sumaPorMoneda($facturas) }}</td>
        </tr>
    </table>

    {{-- Resumen por Proveedor --}}
    @if($facturas->count() > 0)
        @php $porProveedor = $facturas->groupBy('proveedor_id'); @endphp
        <table class="detalles-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Proveedor</th>
                    <th>Facturas</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porProveedor as $index => $facturasProveedor)
                @php $proveedor = $facturasProveedor->first()->proveedor; @endphp
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $proveedor?->razon_social ?? 'Sin proveedor' }}</td>
                    <td class="text-center">{{ $facturasProveedor->count() }}</td>
                    <td class="text-right">{{ $sumaPorMoneda($facturasProveedor) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-right">TOTAL</td>
                    <td class="text-center">{{ $facturas->count() }}</td>
                    <td class="text-right">{{ $sumaPorMoneda($facturas) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
    <p style="margin: 20px 0; text-align: center;">No se encontraron facturas en esta semana.</p>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Merida- Peto Km1, Lote g1 g2 g3 Skypark, Tablaje Catastral 16704 | Kanasin, Yucatan, Mexico<br>
        Tel: 999 454 06 00 al 0689
        <div class="footer-code">Generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
