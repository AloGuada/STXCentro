<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Solicitudes y Requisiciones</title>
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

        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 16px 0 6px;
            padding-bottom: 3px;
            border-bottom: 2px solid #000;
        }
        .section-title span { font-weight: normal; font-size: 9px; color: #555; }

        .rep-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .rep-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
            font-weight: bold;
            background-color: #e5e7eb;
            text-align: center;
        }
        .rep-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        .rep-table tr:nth-child(even) td { background-color: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .empty {
            padding: 20px;
            text-align: center;
            border: 1px dashed #999;
            color: #666;
            font-size: 10px;
            margin: 6px 0 12px;
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
                    SOLICITUDES Y REQUISICIONES<br>{{ $fechaGeneracion->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabla 1: Solicitudes de pago --}}
    <div class="section-title">
        Solicitudes de pago <span>· {{ $solicitudes->count() }} registro(s)</span>
    </div>

    @if($solicitudes->isEmpty())
        <div class="empty">No hay solicitudes de pago para mostrar.</div>
    @else
        <table class="rep-table">
            <thead>
                <tr>
                    <th style="width: 26px;">#</th>
                    <th style="width: 80px;">Folio</th>
                    <th>Solicitante</th>
                    <th>Departamento</th>
                    <th>Proveedor</th>
                    <th>Concepto</th>
                    <th style="width: 90px;">Total</th>
                    <th style="width: 80px;">Estatus</th>
                    <th style="width: 70px;">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($solicitudes as $i => $sol)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $sol->folio }}</td>
                        <td>{{ $sol->solicitante?->name ?? '-' }}</td>
                        <td>{{ $sol->departamento?->descripcion ?? '-' }}</td>
                        <td>{{ $sol->proveedor?->razon_social ?? '-' }}</td>
                        <td>{{ $sol->concepto }}</td>
                        <td class="text-right">${{ number_format((float) $sol->monto_total, 2) }}</td>
                        <td class="text-center">{{ $sol->estatus->label() }}</td>
                        <td class="text-center">{{ $sol->created_at?->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right"><strong>Total</strong></td>
                    <td class="text-right"><strong>${{ number_format((float) $solicitudes->sum('monto_total'), 2) }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Tabla 2: Requisiciones --}}
    <div class="section-title">
        Requisiciones <span>· {{ $requisiciones->count() }} registro(s)</span>
    </div>

    @if($requisiciones->isEmpty())
        <div class="empty">No hay requisiciones para mostrar.</div>
    @else
        <table class="rep-table">
            <thead>
                <tr>
                    <th style="width: 26px;">#</th>
                    <th style="width: 80px;">Folio</th>
                    <th>Solicitante</th>
                    <th>Departamento</th>
                    <th style="width: 100px;">Estatus</th>
                    <th style="width: 80px;">Fecha requerida</th>
                    <th style="width: 70px;">Creada</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requisiciones as $i => $req)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $req->folio }}</td>
                        <td>{{ $req->solicitante?->name ?? '-' }}</td>
                        <td>{{ $req->departamento?->descripcion ?? '-' }}</td>
                        <td class="text-center">{{ $req->estatus->label() }}</td>
                        <td class="text-center">{{ $req->fecha_requerida?->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-center">{{ $req->created_at?->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Generado: {{ $fechaGeneracion->format('d/m/Y H:i:s') }}</span>
        <span>Steelex · Módulo de Costos</span>
    </div>
</body>
</html>
