@php
    $money = fn ($n) => '$' . number_format((float) $n, 2, '.', ',');
    $num = fn ($n, $d = 2) => number_format((float) $n, $d, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carátula de Cotización - {{ $obra['nombre'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; line-height: 1.4; padding: 24px 32px; }
        .title { font-size: 16px; font-weight: bold; }
        .subtitle { font-size: 10px; color: #444; margin-bottom: 12px; }
        .total-box { float: right; border: 1px solid #000; padding: 6px 12px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #999; padding: 5px 8px; }
        th { background: #eee; text-align: left; font-size: 10px; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #f4f4f4; }
        .note { margin-top: 14px; font-size: 8px; color: #777; }
    </style>
</head>
<body>
    <div class="total-box">Importe total de venta: {{ $money($importeTotalVenta) }}</div>
    <div class="title">Carátula de Cotización</div>
    <div class="subtitle">
        {{ $obra['nombre'] }}@if (!empty($obra['op'])) &middot; OP {{ $obra['op'] }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Tarjeta / Nave</th>
                <th class="num">Kg reales</th>
                <th class="num">m&sup2; pintura</th>
                <th class="num">Importe materiales</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($columnas as $c)
                <tr>
                    <td>{{ $c['nombre'] }}</td>
                    <td class="num">{{ $num($c['kg']) }}</td>
                    <td class="num">{{ $num($c['m2_pintura']) }}</td>
                    <td class="num">{{ $money($c['importe_materiales']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#999;">Sin tarjetas en la obra.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOTALES</td>
                <td class="num">{{ $num($obraTotales['kg_total']) }} kg</td>
                <td class="num">{{ $num($obraTotales['m2_montaje_total']) }} m&sup2;</td>
                <td class="num">{{ $money($importeTotalVenta) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="note">Documento generado automáticamente desde el módulo de Cotización.</div>
</body>
</html>
