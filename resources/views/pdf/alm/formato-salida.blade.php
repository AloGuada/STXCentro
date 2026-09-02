<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vale de salida {{ $salida->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
            padding: 10px 40px;
        }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 170px; }
        .logo-cell img { max-width: 160px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-address { font-size: 8.5px; line-height: 1.35; margin-top: 3px; }
        .code-cell { width: 250px; text-align: right; vertical-align: top; }
        .code-title { font-size: 13px; font-weight: bold; margin-bottom: 5px; border-bottom: 2px solid #000; padding-bottom: 3px; }
        .code-folio { font-size: 12px; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; margin-bottom: 4px; }
        .sello { float: right; }

        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 12px 0; }

        .cancelada {
            border: 2px solid #000;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 3px;
            padding: 5px;
            margin-bottom: 10px;
        }
        .cancelada .detalle { font-size: 9px; font-weight: normal; letter-spacing: 0; margin-top: 2px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 22%; }

        .detalles-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .detalles-table th {
            border: 1px solid #000; padding: 4px 8px; font-size: 10px;
            font-weight: bold; background-color: #f0f0f0; text-align: center;
        }
        .detalles-table td { border: 1px solid #000; padding: 4px 8px; font-size: 10px; }
        .detalles-table .text-right { text-align: right; }
        .detalles-table .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .detalles-table .obs { font-size: 8.5px; font-style: italic; }

        .firmas-table { width: 100%; margin-top: 42px; }
        .firmas-table td { width: 50%; text-align: center; font-size: 9.5px; padding: 0 24px; vertical-align: bottom; }
        .firma-linea { border-top: 1px solid #000; padding-top: 3px; }
        .firma-nombre { font-weight: bold; font-size: 10px; }
        .firma-rol { color: #333; }

        .aviso {
            position: fixed;
            bottom: 16px;
            left: 40px;
            right: 40px;
            font-size: 8px;
            line-height: 1.4;
            border-top: 1px solid #000;
            padding-top: 4px;
            text-align: justify;
        }
    </style>
</head>
<body>
    @php
        $folio = $salida->folio ?? ('SAL-'.str_pad((string) $salida->id, 4, '0', STR_PAD_LEFT));
        $total = 0.0;
        foreach ($salida->detalles as $d) {
            $total += $d->importe();
        }
    @endphp

    {{-- Encabezado --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo', ['width' => 160])
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-address">
                    Carretera Mérida KM1, Lote G1,G2,G3, Tablaje Catastral 16704<br>
                    Kanasín, Yucatán C.P. 97370<br>
                    Tel: 999-454-06-00<br>
                    R.F.C. TMA9405205F5<br>
                    www.steelex.com.mx
                </div>
            </td>
            <td class="code-cell">
                <div class="code-title">VALE DE SALIDA</div>
                <div class="code-folio">{{ $folio }}</div>
                {{-- El sello: la hoja vuelve firmada y hay que reencontrarla en
                     el sistema. Escanearla es un tiro; teclear el folio es un
                     dígito equivocado y un documento que no aparece. --}}
                <div class="sello">
                    @include('pdf.partials.codigo-barras', ['valor' => $folio, 'mostrarTexto' => false])
                </div>
            </td>
        </tr>
    </table>

    <div class="title">ENTREGA DE MATERIAL DE ALMACÉN</div>

    @if ($salida->estaCancelada())
        <div class="cancelada">
            CANCELADA
            <div class="detalle">
                {{ $salida->cancelada_at?->format('d/m/Y H:i') }} — {{ $salida->motivo_cancelacion ?: 'Sin motivo asentado' }}.
                El material volvió al kardex con un movimiento espejo: esta hoja ya no ampara nada.
            </div>
        </div>
    @endif

    {{-- Datos del documento --}}
    <table class="info-table">
        <tr>
            <td class="label">Fecha</td>
            <td>{{ $salida->fecha?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Almacén</td>
            <td>{{ trim(($salida->almacen?->clave ? $salida->almacen->clave.' - ' : '').($salida->almacen?->nombre ?? '')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Recibe</td>
            <td><strong>{{ $salida->recibe_nombre ?: '-' }}</strong></td>
            <td class="label">Módulo</td>
            <td>{{ $salida->grupoTrabajo?->descripcion ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $salida->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Surte el pedido</td>
            <td>{{ $salida->pedido?->folio ?? 'Salida directa, sin pedido' }}</td>
        </tr>
        <tr>
            <td class="label">Entregó</td>
            <td>{{ $salida->entregador?->name ?? '-' }}</td>
            <td class="label">Registrada</td>
            <td>{{ $salida->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        @if ($salida->observaciones)
        <tr>
            <td class="label">Observaciones</td>
            <td colspan="3">{{ $salida->observaciones }}</td>
        </tr>
        @endif
    </table>

    {{-- Lo que se entrega --}}
    <table class="detalles-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave</th>
                <th>Descripción</th>
                <th style="width: 14%;">Cantidad</th>
                <th style="width: 15%;">Costo unitario</th>
                <th style="width: 15%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salida->detalles as $d)
            <tr>
                <td>{{ $d->producto?->codigo ?? '-' }}</td>
                <td>
                    {{ $d->producto?->descripcion ?? '-' }}
                    @if ($d->observaciones)
                        <div class="obs">{{ $d->observaciones }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $d->cantidad, 2) }} {{ $d->producto?->unidad }}</td>
                <td class="text-right">{{ $d->costo_unitario === null ? '-' : '$'.number_format((float) $d->costo_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->importe(), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL</td>
                <td class="text-right">{{ $salida->detalles->count() }} renglón(es)</td>
                <td></td>
                <td class="text-right">${{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Firmas: la razón de ser del vale es que regrese con la de quien se
         llevó el material. --}}
    <table class="firmas-table">
        <tr>
            <td>
                <div class="firma-linea">
                    <div class="firma-nombre">{{ $salida->entregador?->name ?? '' }}</div>
                    <div class="firma-rol">Entregó - Almacén</div>
                </div>
            </td>
            <td>
                <div class="firma-linea">
                    <div class="firma-nombre">{{ $salida->recibe_nombre ?: '' }}</div>
                    <div class="firma-rol">Recibió de conformidad</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="aviso">
        Quien firma de recibido responde por el material amparado en este vale hasta su consumo o devolución.
        Cualquier diferencia debe reclamarse en el momento de la entrega: una vez firmado, el almacén considera surtida la cantidad asentada.
    </div>
</body>
</html>
