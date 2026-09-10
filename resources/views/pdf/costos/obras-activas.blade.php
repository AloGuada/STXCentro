{{--
    Impresión de la pantalla "Obras activas": el mismo listado que se ve, con
    su filtro y su orden, sin paginar. Es una hoja de consulta, no un formato
    que se firme, así que no lleva espacios de firma.
--}}
@php
    $etiquetasTipo = ['proyecto' => 'Proyecto', 'obra' => 'Obra', 'partida' => 'Partida'];
    $titulo = $estatus === 'cerrado' ? 'OBRAS CERRADAS' : 'OBRAS ACTIVAS';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
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
        .code-cell { width: 140px; text-align: right; }
        .code-box { border: 1px solid #000; padding: 4px 8px; font-size: 9px; font-weight: bold; display: inline-block; }

        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 12px 0 6px; }
        .subtitle { text-align: center; font-size: 11px; margin-bottom: 15px; color: #555; }

        .obras-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .obras-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
            font-weight: bold;
            background-color: #e5e7eb;
            text-align: center;
        }
        .obras-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        .obras-table tr:nth-child(even) td { background-color: #f9fafb; }
        .text-center { text-align: center; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .muted { color: #777; }

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
        }
        .footer .right { float: right; }
    </style>
</head>
<body>
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
                    {{ $titulo }}<br>{{ $fechaGeneracion->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title">{{ $titulo }}</div>
    <div class="subtitle">
        Total: {{ $presupuestos->count() }}
        @if($busqueda)
            &nbsp;·&nbsp; Filtrado por &laquo;{{ $busqueda }}&raquo;
        @endif
    </div>

    @if($presupuestos->isEmpty())
        <div class="empty">
            No hay {{ $estatus === 'cerrado' ? 'obras cerradas' : 'obras activas' }} con ese filtro.
        </div>
    @else
        <table class="obras-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th style="width: 70px;">Tipo</th>
                    <th style="width: 200px;">Nombre</th>
                    <th style="width: 90px;">OP</th>
                    <th>Descripción</th>
                    <th style="width: 70px;">Presupuesto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($presupuestos as $i => $p)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center">
                            {{ $p['es_planta'] ? 'Planta' : ($etiquetasTipo[$p['tipo']] ?? 'Obra') }}
                        </td>
                        <td>{{ $p['nombre'] }}</td>
                        <td class="mono">{{ $p['op'] ?? '—' }}</td>
                        <td>{{ $p['descripcion'] ?? '—' }}</td>
                        {{-- Si el documento autorizado ya se cargó, la hoja impresa lo
                             dice: es la pregunta que trae a alguien a esta pantalla. --}}
                        <td class="text-center">
                            @if($p['documento'])
                                Sí
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Generado: {{ $fechaGeneracion->format('d/m/Y H:i:s') }}</span>
        <span class="right">Steelex · Módulo de Costos</span>
    </div>
</body>
</html>
