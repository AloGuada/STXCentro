<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Presupuestos</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7px;
            color: #000;
            line-height: 1.2;
            padding: 8px 12px;
        }

        .header-table {
            width: 100%;
            margin-bottom: 5px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 140px;
        }
        .logo-cell img {
            max-width: 130px;
        }
        .company-cell {
            text-align: center;
        }
        .company-name {
            font-size: 12px;
            font-weight: bold;
        }
        .company-url {
            font-size: 8px;
            color: #0563C1;
        }
        .company-dept {
            font-size: 8px;
            font-weight: bold;
        }
        .code-cell {
            width: 140px;
            text-align: right;
            font-size: 8px;
        }

        .title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            margin: 6px 0;
        }
        .fecha-gen {
            text-align: center;
            font-size: 8px;
            color: #555;
            margin-bottom: 6px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            border: 1px solid #666;
            padding: 2px 3px;
            font-size: 6.5px;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #2c6e9e;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        .th-materiales {
            background-color: #1a4d73;
        }
        .th-ingresos {
            background-color: #6aa648;
        }
        .th-total {
            background-color: #3b7f3b;
        }
        .th-sub {
            background-color: #d7e4f0;
            color: #000;
            font-weight: bold;
            text-align: center;
        }

        .proyecto-cell {
            font-weight: bold;
            font-size: 7px;
            background-color: #f0f0f0;
            text-align: left;
            padding: 3px 4px;
        }
        .estatus-badge {
            display: inline-block;
            padding: 1px 3px;
            border-radius: 3px;
            background-color: #e0e0e0;
            font-size: 6px;
            font-weight: normal;
            text-transform: capitalize;
        }

        .label-cell {
            background-color: #f7f7f7;
            font-weight: bold;
            text-align: center;
            font-size: 6.5px;
        }

        .num {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 6.5px;
        }
        .neg {
            color: #b00020;
        }
        .pct-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background-color: #fffde7;
        }
        .highlight {
            background-color: #fff9c4;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="logo-cell">
            @include('pdf.partials.logo', ['width' => 130])
        </td>
        <td class="company-cell">
            <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
            <div class="company-url">www.steelex.com.mx</div>
            <div class="company-dept">COSTOS</div>
        </td>
        <td class="code-cell">
            Generado: {{ $fechaGeneracion->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>

<div class="title">REPORTE DE PRESUPUESTOS</div>

@php
    // Contar rubros totales para colspan base
    $totalRubrosCols = $tipos->sum(fn($t) => $t->rubros->count());
@endphp

<table class="data-table">
    {{-- Header de 2 niveles --}}
    <thead>
        <tr>
            <th rowspan="2" style="width: 55px;">PROYECTO</th>
            <th rowspan="2" style="width: 130px;">NOMBRE DEL PROYECTO</th>
            <th rowspan="2" style="width: 20px;"></th>
            <th colspan="1" rowspan="2" class="th-ingresos" style="width: 70px;">INGRESOS</th>
            @foreach($tipos as $tipo)
                <th colspan="{{ 2 + $tipo->rubros->count() }}" class="th-materiales">
                    {{ strtoupper($tipo->descripcion) }}
                </th>
            @endforeach
            <th colspan="6" class="th-total">TOTALES</th>
        </tr>
        <tr>
            @foreach($tipos as $tipo)
                <th class="th-sub" style="width: 60px;">TOTAL {{ strtoupper($tipo->descripcion) }}</th>
                <th class="th-sub" style="width: 30px;">%</th>
                @foreach($tipo->rubros as $rubro)
                    <th class="th-sub" style="width: 55px;" title="{{ $rubro->descripcion }}">
                        {{ strtoupper($rubro->codigo) }}
                    </th>
                @endforeach
            @endforeach
            <th class="th-sub" style="width: 60px;">TOTAL</th>
            <th class="th-sub" style="width: 30px;">%</th>
            <th class="th-sub" style="width: 60px;">Utilidad</th>
            <th class="th-sub" style="width: 35px;">% UTIL VTS</th>
            <th class="th-sub" style="width: 30px;">Gtos. 5.5%</th>
            <th class="th-sub" style="width: 35px;">% UTILIDAD</th>
        </tr>
    </thead>

    <tbody>
        @foreach($filas as $fila)
            @php
                $obra = $fila['obra'];
                $ingresos = ['presup' => $fila['ingresos_presup'], 'real' => $fila['ingresos_real'], 'dif' => $fila['ingresos_dif']];
                $tipos_fila = $fila['tipos'];
            @endphp

            {{-- Fila PRESUP --}}
            <tr>
                <td rowspan="3" class="proyecto-cell">
                    {{ $obra->no }}
                </td>
                <td rowspan="3" class="proyecto-cell">
                    {{ $obra->descripcion }}<br>
                    <span class="estatus-badge">{{ $obra->estatus }}</span>
                </td>
                <td class="label-cell">PRESUP.</td>
                <td class="num">${{ number_format($ingresos['presup'], 2) }}</td>

                @foreach($tipos_fila as $td)
                    <td class="num">${{ number_format($td['total_presup'], 2) }}</td>
                    <td rowspan="3" class="pct-cell">{{ number_format($td['porcentaje'], 2) }}%</td>
                    @foreach($td['rubros'] as $rd)
                        <td class="num">${{ number_format($rd['presup'], 2) }}</td>
                    @endforeach
                @endforeach

                <td class="num">${{ number_format($fila['total_presup'], 2) }}</td>
                <td rowspan="3" class="pct-cell">{{ number_format($fila['total_pct'], 2) }}%</td>
                <td rowspan="3" class="num highlight">${{ number_format($fila['utilidad'], 2) }}</td>
                <td rowspan="3" class="pct-cell {{ $fila['util_vts_pct'] < 0 ? 'neg' : '' }}">
                    {{ number_format($fila['util_vts_pct'], 2) }}%
                </td>
                <td rowspan="3" class="pct-cell">5.5%</td>
                <td rowspan="3" class="pct-cell {{ $fila['utilidad_pct'] < 0 ? 'neg' : '' }}">
                    {{ number_format($fila['utilidad_pct'], 2) }}%
                </td>
            </tr>

            {{-- Fila REAL --}}
            <tr>
                <td class="label-cell">REAL</td>
                <td class="num">${{ number_format($ingresos['real'], 2) }}</td>

                @foreach($tipos_fila as $td)
                    <td class="num">${{ number_format($td['total_real'], 2) }}</td>
                    @foreach($td['rubros'] as $rd)
                        <td class="num">${{ number_format($rd['real'], 2) }}</td>
                    @endforeach
                @endforeach

                <td class="num">${{ number_format($fila['total_real'], 2) }}</td>
            </tr>

            {{-- Fila DIF --}}
            <tr>
                <td class="label-cell">DIF.</td>
                <td class="num {{ $ingresos['dif'] < 0 ? 'neg' : '' }}">${{ number_format($ingresos['dif'], 2) }}</td>

                @foreach($tipos_fila as $td)
                    <td class="num {{ $td['total_dif'] < 0 ? 'neg' : '' }}">${{ number_format($td['total_dif'], 2) }}</td>
                    @foreach($td['rubros'] as $rd)
                        <td class="num {{ $rd['dif'] < 0 ? 'neg' : '' }}">${{ number_format($rd['dif'], 2) }}</td>
                    @endforeach
                @endforeach

                <td class="num {{ $fila['total_dif'] < 0 ? 'neg' : '' }}">${{ number_format($fila['total_dif'], 2) }}</td>
            </tr>
        @endforeach

        @if($filas->isEmpty())
            <tr>
                <td colspan="{{ 4 + (2 * $tipos->count()) + $totalRubrosCols + 6 }}" style="text-align: center; padding: 20px; color: #666;">
                    No hay obras registradas.
                </td>
            </tr>
        @endif
    </tbody>
</table>

</body>
</html>
