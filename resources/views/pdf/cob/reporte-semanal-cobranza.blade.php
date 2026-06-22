<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Semanal de Cobranza</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; line-height: 1.3; padding: 10px 20px; }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 150px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 12px; font-weight: bold; }
        .company-url { font-size: 9px; color: #0563C1; }
        .company-dept { font-size: 9px; font-weight: bold; }
        .code-cell { width: 120px; text-align: center; vertical-align: top; }
        .code-box { border: 1px solid #000; padding: 4px 8px; font-size: 9px; font-weight: bold; display: inline-block; }
        .title { text-align: center; font-size: 12px; font-weight: bold; margin: 10px 0 2px; }
        .subtitle { text-align: center; font-size: 9px; margin-bottom: 10px; color: #444; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table th { border: 1px solid #000; padding: 3px 5px; font-size: 8px; font-weight: bold; background-color: #f0f0f0; }
        .data-table td { border: 1px solid #000; padding: 3px 5px; font-size: 8px; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .section-title { font-size: 10px; font-weight: bold; margin: 8px 0 4px; }
        .saldo-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .saldo-grid td { border: 1px solid #000; padding: 5px 8px; width: 25%; }
        .saldo-grid .lbl { font-size: 8px; color: #555; }
        .saldo-grid .val { font-size: 11px; font-weight: bold; }
        .saldo-grid .val-sm { font-size: 8px; color: #444; }
        .notas-box { border: 1px solid #000; padding: 6px 8px; min-height: 40px; white-space: pre-wrap; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #555; padding: 8px 20px; }
    </style>
</head>
<body>
    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2);
        $grupos = collect($r['cobros'])->groupBy('obra_no');
    @endphp

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">@include('pdf.partials.logo', ['width' => 140])</td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-url">www.steelex.com.mx</div>
                <div class="company-dept">COBRANZA</div>
            </td>
            <td class="code-cell">
                <div class="code-box">REPORTE<br>SEMANAL</div>
            </td>
        </tr>
    </table>

    <div class="title">REPORTE SEMANAL DE COBRANZA</div>
    <div class="subtitle">
        Año {{ $r['anio'] }} · Semana {{ $r['semana'] }}
        ({{ \Illuminate\Support\Carbon::parse($r['fecha_inicio'])->format('d/m/Y') }}
        – {{ \Illuminate\Support\Carbon::parse($r['fecha_fin'])->format('d/m/Y') }})
    </div>

    {{-- Resumen de saldos --}}
    <table class="saldo-grid">
        <tr>
            <td><div class="lbl">Saldo anterior</div><div class="val">{{ $money($r['saldo_anterior_sin_iva']) }}</div><div class="val-sm">{{ $money($r['saldo_anterior_con_iva']) }} c/IVA</div></td>
            <td><div class="lbl">+ Detonaciones</div><div class="val">{{ $money($r['total_detonaciones_sin_iva']) }}</div><div class="val-sm">{{ $money($r['total_detonaciones_con_iva']) }} c/IVA</div></td>
            <td><div class="lbl">− Cobrado</div><div class="val">{{ $money($r['total_cobrado_sin_iva']) }}</div><div class="val-sm">{{ $money($r['total_cobrado_con_iva']) }} c/IVA</div></td>
            <td style="background-color:#f0f0f0;"><div class="lbl">= NUEVO SALDO</div><div class="val">{{ $money($r['saldo_nuevo_sin_iva']) }}</div><div class="val-sm">{{ $money($r['saldo_nuevo_con_iva']) }} c/IVA</div></td>
        </tr>
    </table>

    {{-- Detonaciones --}}
    <div class="section-title">Detonaciones de obras (suman al saldo)</div>
    <table class="data-table">
        <thead><tr><th>Obra</th><th>Descripción</th><th class="text-right">Sin IVA</th><th class="text-right">Con IVA</th></tr></thead>
        <tbody>
            @forelse ($r['detonaciones'] as $d)
                <tr>
                    <td>{{ $d['obra_no'] }}</td>
                    <td>{{ $d['descripcion'] }}</td>
                    <td class="text-right">{{ $money($d['monto_sin_iva']) }}</td>
                    <td class="text-right">{{ $money($d['monto_con_iva']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">Sin obras nuevas en la semana</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2" class="text-right">Total detonaciones</td>
                <td class="text-right">{{ $money($r['total_detonaciones_sin_iva']) }}</td>
                <td class="text-right">{{ $money($r['total_detonaciones_con_iva']) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Cobros agrupados por obra --}}
    <div class="section-title">Estimaciones cobradas por obra (restan al saldo)</div>
    <table class="data-table">
        <thead><tr><th>Obra</th><th>Estimación</th><th class="text-right">Sin IVA</th><th class="text-right">Con IVA</th></tr></thead>
        <tbody>
            @forelse ($grupos as $obraNo => $cobros)
                @foreach ($cobros as $c)
                    <tr>
                        <td>{{ $loop->first ? $obraNo : '' }}</td>
                        <td>Est. #{{ $c['numero_estimacion'] }}</td>
                        <td class="text-right">{{ $money($c['monto_sin_iva']) }}</td>
                        <td class="text-right">{{ $money($c['monto_con_iva']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" class="text-right" style="font-style:italic;">Subtotal {{ $obraNo }}</td>
                    <td class="text-right">{{ $money(collect($cobros)->sum('monto_sin_iva')) }}</td>
                    <td class="text-right">{{ $money(collect($cobros)->sum('monto_con_iva')) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">Sin estimaciones cobradas en la semana</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2" class="text-right">Total cobrado</td>
                <td class="text-right">{{ $money($r['total_cobrado_sin_iva']) }}</td>
                <td class="text-right">{{ $money($r['total_cobrado_con_iva']) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Notas --}}
    <div class="section-title">Notas</div>
    <div class="notas-box">{{ $r['notas'] ?: 'Sin notas.' }}</div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · TIM DEL MAYAB, S.A. DE C.V.
    </div>
</body>
</html>
