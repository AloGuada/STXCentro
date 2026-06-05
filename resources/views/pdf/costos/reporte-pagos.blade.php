<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.4;
            padding: 15px 25px;
        }
        .header-table { width: 100%; margin-bottom: 10px; }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 160px; }
        .logo-cell img { max-width: 150px; }
        .company-cell { text-align: center; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-url { font-size: 9px; color: #0563C1; }
        .company-dept { font-size: 9px; font-weight: bold; }
        .code-cell { width: 120px; text-align: right; }
        .code-box { border: 1px solid #000; padding: 4px 8px; font-size: 9px; font-weight: bold; display: inline-block; }

        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 12px 0 6px; }
        .subtitle { text-align: center; font-size: 11px; margin-bottom: 15px; color: #555; }

        .pagos-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .pagos-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
            font-weight: bold;
            background-color: #e5e7eb;
            text-align: center;
        }
        .pagos-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        .pagos-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .capitalize { text-transform: capitalize; }

        .empty {
            padding: 30px;
            text-align: center;
            border: 1px dashed #999;
            color: #666;
            font-size: 11px;
            margin: 20px 0;
        }

        .footer {
            margin-top: 15px;
            padding-top: 6px;
            border-top: 1px solid #ccc;
            font-size: 8px;
            color: #555;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo', ['width' => 150])
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">COSTOS</div>
            </td>
            <td class="code-cell">
                <div class="code-box">
                    REPORTE DE PAGOS<br>{{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title">REPORTE DE PAGOS</div>
    <div class="subtitle">
        Del {{ $fechaInicio->format('d/m/Y') }} al {{ $fechaFin->format('d/m/Y') }}
        &nbsp;·&nbsp; Total registros: {{ $pagos->count() }}
    </div>

    @if($pagos->isEmpty())
        <div class="empty">No se encontraron pagos en el rango seleccionado.</div>
    @else
        <table class="pagos-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th style="width: 90px;">Folio</th>
                    <th>Proveedor</th>
                    <th style="width: 110px;">Cantidad</th>
                    <th style="width: 70px;">Tipo</th>
                    <th style="width: 90px;">Fecha creación</th>
                    <th style="width: 90px;">Método</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $i => $pago)
                    @php
                        $pagable = $pago->pagable;
                        $proveedor = $pagable && method_exists($pagable, 'proveedor') ? $pagable->proveedor : null;
                        $metodo = $pagable && isset($pagable->tipo_pago) ? $pagable->tipo_pago : null;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $pago->folio }}</td>
                        <td>{{ $proveedor?->razon_social ?? '-' }}</td>
                        <td class="text-right">
                            ${{ number_format($pago->monto_pago, 2) }} {{ strtoupper($pago->moneda ?? 'MXN') }}
                        </td>
                        <td class="capitalize text-center">{{ $pago->tipo_pago }}</td>
                        <td class="text-center">{{ $pago->created_at->format('d/m/Y') }}</td>
                        <td class="capitalize text-center">{{ $metodo ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Generado: {{ now()->format('d/m/Y H:i:s') }}</span>
        <span>Steelex · Módulo de Costos</span>
    </div>
</body>
</html>
