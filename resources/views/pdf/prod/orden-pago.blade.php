<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Pago {{ $destajo->anio }} S{{ $destajo->semana }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; line-height: 1.35; padding: 12px 28px; }

        .header-table { width: 100%; margin-bottom: 6px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 170px; }
        .logo-cell img { max-width: 160px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 13px; font-weight: bold; }
        .company-address { font-size: 8px; line-height: 1.3; margin-top: 3px; }
        .code-cell { width: 190px; text-align: center; vertical-align: top; }
        .code-title { font-size: 13px; font-weight: bold; margin-bottom: 6px; border-bottom: 2px solid #000; padding-bottom: 3px; }
        .code-box { font-size: 9px; text-align: left; }
        .code-box .label { font-weight: bold; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { border: 1px solid #000; padding: 3px 6px; font-size: 9.5px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; }

        .section-title { font-size: 10.5px; font-weight: bold; margin: 8px 0 3px; text-transform: uppercase; }

        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.grid th { border: 1px solid #000; padding: 3px 5px; font-size: 8.5px; font-weight: bold; background-color: #f0f0f0; text-align: center; }
        table.grid td { border: 1px solid #000; padding: 3px 5px; font-size: 8.5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .empty-cell { color: #666; font-style: italic; text-align: center; }

        .cols { width: 100%; }
        .cols td { vertical-align: top; }
        .col-left { width: 62%; padding-right: 10px; }
        .col-right { width: 38%; }

        .totales td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .totales .label { font-weight: bold; background-color: #f0f0f0; }
        .totales .grand td { font-weight: bold; font-size: 11px; background-color: #e0e0e0; }

        .firma { margin-top: 26px; text-align: center; width: 300px; }
        .firma-line { border-top: 1px solid #000; padding-top: 3px; font-size: 9px; }
        .firma-line .cargo { font-weight: bold; }

        .form-code { margin-top: 10px; font-size: 8px; color: #333; }
        .no-data { text-align: center; font-size: 12px; padding: 40px; color: #555; }
    </style>
</head>
<body>
@php
    $periodo = trim(optional($destajo->fecha_inicio)->format('d/m/Y').' — '.optional($destajo->fecha_fin)->format('d/m/Y'), ' —');
    $mon = fn ($n) => '$'.number_format((float) $n, 2);
@endphp

@forelse($grupos as $g)
<div @if(!$loop->last) style="page-break-after: always;" @endif>
    {{-- Header estilo costos --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">@include('pdf.partials.logo')</td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-address">
                    Carretera Mérida KM1, Lote G1,G2,G3, Tablaje Catastral 16704<br>
                    Kanasín, Yucatán C.P. 97370<br>
                    Tel: 999-454-06-00 · R.F.C. TMA9405205F5<br>
                    www.steelex.com.mx
                </div>
            </td>
            <td class="code-cell">
                <div class="code-title">ORDEN DE PAGO</div>
                <div class="code-box">
                    <span class="label">Semana:</span> {{ $destajo->semana }} / {{ $destajo->anio }}<br>
                    <span class="label">Periodo:</span> {{ $periodo ?: '-' }}<br>
                    <span class="label">Estatus:</span> {{ $destajo->cerrado ? 'Cerrado' : 'Preliminar' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Info del grupo --}}
    <table class="info-table">
        <tr>
            <td class="label" style="width: 12%;">Grupo</td>
            <td style="width: 38%;"><strong>{{ $g['grupo']['descripcion'] }}</strong></td>
            <td class="label" style="width: 12%;">Línea</td>
            <td style="width: 13%;">{{ $g['grupo']['linea'] ?? '-' }}</td>
            <td class="label" style="width: 12%;">Módulo</td>
            <td style="width: 13%;">{{ $g['grupo']['modulo'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- Piezas fabricadas --}}
    <div class="section-title">Piezas fabricadas</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 11%;">Marca</th>
                <th>Descripción</th>
                <th style="width: 16%;">Obra</th>
                <th style="width: 6%;">Pzs</th>
                <th style="width: 5%;">%</th>
                <th style="width: 8%;">Largo (mm)</th>
                <th style="width: 9%;">Peso u. (kg)</th>
                <th style="width: 9%;">Kilos</th>
                <th style="width: 8%;">$/kg</th>
                <th style="width: 11%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($g['piezas'] as $p)
            <tr>
                <td>{{ $p['marca'] }}</td>
                <td>{{ $p['descripcion'] }}</td>
                <td>{{ $p['obra'] }}</td>
                <td class="text-right">{{ number_format($p['pzs']) }}</td>
                <td class="text-right">{{ rtrim(rtrim(number_format($p['porcentaje'] ?? 100, 2), '0'), '.') }}%</td>
                <td class="text-right">{{ $p['largo'] !== null ? number_format($p['largo']) : '-' }}</td>
                <td class="text-right">{{ $p['peso_unitario'] !== null ? number_format($p['peso_unitario'], 3) : '-' }}</td>
                <td class="text-right">{{ number_format($p['kilos'], 3) }}</td>
                <td class="text-right">{{ number_format($p['precio_kilo'], 4) }}</td>
                <td class="text-right">{{ $mon($p['importe']) }}</td>
            </tr>
            @empty
            <tr><td colspan="10" class="empty-cell">Sin producción capturada</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-right">TOTAL PRODUCCIÓN</td>
                <td class="text-right">{{ number_format($g['total_kilos'], 3) }}</td>
                <td></td>
                <td class="text-right">{{ $mon($g['total_produccion']) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="cols">
        <tr>
            {{-- Pagos extra (una sola tabla; el tipo es una columna) --}}
            <td class="col-left">
                @php
                    $pagosPlanos = collect($g['secciones'])
                        ->flatMap(fn ($sec) => collect($sec['pagos'])->map(fn ($p) => $p + ['tipo' => $sec['tipo']]))
                        ->all();
                @endphp
                <div class="section-title">Pagos extra</div>
                <table class="grid">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Tipo</th>
                            <th>Descripción</th>
                            <th style="width: 14%;">Precio</th>
                            <th style="width: 9%;">Días</th>
                            <th style="width: 10%;"># Pers.</th>
                            <th style="width: 16%;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pagosPlanos as $pago)
                        <tr>
                            <td>{{ $pago['tipo'] }}</td>
                            <td>{{ $pago['descripcion'] ?: '-' }}</td>
                            <td class="text-right">{{ $mon($pago['precio']) }}</td>
                            <td class="text-center">{{ $pago['dias'] }}</td>
                            <td class="text-center">{{ $pago['personas'] }}</td>
                            <td class="text-right">{{ $mon($pago['importe']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="empty-cell">Sin pagos extra</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5" class="text-right">TOTAL PAGOS EXTRA</td>
                            <td class="text-right">{{ $mon($g['total_extras']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>

            {{-- Totales + distribución --}}
            <td class="col-right">
                <div class="section-title">Resumen</div>
                <table class="totales" style="width: 100%; border-collapse: collapse;">
                    <tr><td class="label">Producción</td><td class="text-right">{{ $mon($g['total_produccion']) }}</td></tr>
                    <tr><td class="label">Pagos extra</td><td class="text-right">{{ $mon($g['total_extras']) }}</td></tr>
                    <tr class="grand"><td>TOTAL</td><td class="text-right">{{ $mon($g['total_final']) }}</td></tr>
                </table>

                <div class="section-title">Distribución a empleados</div>
                <table class="grid">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Empleado</th>
                            <th style="width: 20%;">%</th>
                            <th style="width: 30%;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($g['empleados'] as $emp)
                        <tr>
                            <td>{{ $emp['nombre'] }} <span style="color:#666;">({{ $emp['no_empleado'] ?: 's/n' }})</span></td>
                            <td class="text-right">{{ number_format($emp['porcentaje'], 2) }}%</td>
                            <td class="text-right">{{ $mon($emp['monto']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="empty-cell">Sin empleados en el grupo</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 20px;"><tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%;">
            <div class="firma">
                <div class="firma-line">
                    <span class="cargo">JEFE DE PRODUCCIÓN</span>
                </div>
            </div>
        </td>
    </tr></table>

    <div class="form-code">F-STX-PR-2T-06 · Revisión: 00</div>
</div>
@empty
<div class="no-data">
    Este destajo no tiene producción ni pagos extra{{ $destajo->cerrado ? ' (sin liquidaciones)' : '' }}.
</div>
@endforelse
</body>
</html>
