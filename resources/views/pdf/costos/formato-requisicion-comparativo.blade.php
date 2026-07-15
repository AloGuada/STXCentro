<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comparativo {{ $requisicion->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; line-height: 1.4; padding: 10px 40px; }
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 180px; }
        .logo-cell img { max-width: 170px; }
        .company-cell { text-align: center; vertical-align: middle; }
        .company-name { font-size: 14px; font-weight: bold; }
        .company-address { font-size: 8.5px; line-height: 1.35; margin-top: 3px; }
        .code-cell { width: 140px; text-align: center; vertical-align: top; }
        .code-title { font-size: 11px; font-weight: bold; margin-bottom: 5px; }
        .code-box { border: 1px solid #000; padding: 4px 10px; font-size: 10px; font-weight: bold; display: inline-block; }
        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 15px 0; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info-table td { border: 1px solid #000; padding: 3px 6px; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 20%; }
        .comp-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .comp-table th, .comp-table td { border: 1px solid #000; padding: 3px 5px; font-size: 9px; }
        .comp-table th { font-weight: bold; background-color: #f0f0f0; text-align: center; }
        .comp-table th.prov { border-left: 2px solid #000; }
        .comp-table td.opcion { border-left: 2px solid #000; }
        .comp-table .text-right { text-align: right; }
        .comp-table .seleccionado { background-color: #cfe2ff; font-weight: bold; }
        .comp-table .total-row td { font-weight: bold; background-color: #f0f0f0; }
        .comp-table .letras-row td { font-size: 8.5px; font-style: italic; text-transform: uppercase; text-align: left; }
        .legend { font-size: 8.5px; color: #555; margin-bottom: 8px; }
        .legend .swatch { display: inline-block; width: 9px; height: 9px; vertical-align: middle; margin: 0 3px 0 10px; border: 1px solid #999; }
        .legend .swatch.sel { background-color: #cfe2ff; }
        .signatures-table { width: 100%; margin-top: 40px; }
        .signatures-table td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-placeholder { height: 50px; }
        .sig-name { font-weight: bold; font-size: 10px; border-top: 1px solid #000; padding-top: 5px; }
        .sig-role { font-size: 9px; color: #555; }
        .sig-date { font-size: 8px; color: #888; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #555; padding: 10px 40px; }
    </style>
</head>
<body>
    @php
        $fecha = $requisicion->created_at ?? now();

        // Columnas = opciones (de proveedor) con al menos un precio capturado,
        // agrupadas por proveedor. Mismo criterio que el comparativo en pantalla.
        $opcionesConPrecio = [];
        foreach ($requisicion->detalles as $d) {
            foreach ($d->cotizaciones as $c) {
                if ($c->opcion_id !== null) {
                    $opcionesConPrecio[$c->opcion_id] = true;
                }
            }
        }
        $opciones = $requisicion->cotizacionOpciones
            ->filter(fn ($o) => isset($opcionesConPrecio[$o->id]));

        // Agrupar por proveedor (encabezado con col-span) y ordenar por nombre.
        $grupos = $opciones
            ->groupBy('proveedor_id')
            ->map(function ($ops) {
                $prov = $ops->first()->proveedor;
                return [
                    'proveedor_id' => (int) $ops->first()->proveedor_id,
                    'nombre' => $prov?->nombre_comercial ?: ($prov?->razon_social ?: '#'.$ops->first()->proveedor_id),
                    'opciones' => $ops->sortBy('orden')->values(),
                ];
            })
            ->sortBy('nombre')
            ->values();
        $columnas = $grupos->flatMap(fn ($g) => $g['opciones']);
        $numCols = $columnas->count();

        $etiquetaOpcion = fn ($o) => $o->etiqueta ?: 'Opción '.$o->orden;

        $cotizacionDe = function ($detalle, $opcionId) {
            return $detalle->cotizaciones->firstWhere('opcion_id', $opcionId);
        };

        // Importe de la partida: suma (cantidad × precio) de las opciones
        // elegidas para la OC. El PDF se emite ya con la OC definida, así que
        // sin selección no hay importe.
        $importeDetalle = function ($d) {
            $total = 0;
            $tiene = false;
            foreach ($d->selecciones as $s) {
                $px = (float) ($s->cotizacionPrecio->precio_unitario ?? 0);
                if ($px > 0) {
                    $total += $px * (float) $s->cantidad;
                    $tiene = true;
                }
            }
            return ['importe' => $total, 'tiene' => $tiene];
        };

        $subtotalComp = 0;
        foreach ($requisicion->detalles as $d) {
            $subtotalComp += $importeDetalle($d)['importe'];
        }
        $ivaComp = $subtotalComp * 0.16;
        $totalComp = $subtotalComp + $ivaComp;
    @endphp

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @include('pdf.partials.logo')
            </td>
            <td class="company-cell">
                <div class="company-name">TIM DEL MAYAB, S.A. DE C.V.</div>
                <div class="company-address">
                    Carretera Mérida KM1, Lote G1,G2,G3, Tablaje Catastral 16704<br>
                    Kanasín, Yucatán C.P. 97370<br>
                    Tel: 999-454-06-00<br>
                    E-mail: facturacion.almacen@steelex.com.mx<br>
                    R.F.C. TMA9405205F5<br>
                    www.steelex.com.mx
                </div>
            </td>
            <td class="code-cell">
                <div class="code-title">COMPARATIVO DE COTIZACIONES</div>
                <div class="code-box">
                    F-STX-COSTOS-CMP<br>REVISION:00
                </div>
            </td>
        </tr>
    </table>

    <div class="title">COMPARATIVO DE COTIZACIONES</div>

    <table class="info-table">
        <tr>
            <td class="label">Folio Requisicion</td>
            <td>{{ $requisicion->folio }}</td>
            <td class="label">Fecha</td>
            <td>{{ $fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $requisicion->departamento?->descripcion ?? '-' }}</td>
            <td class="label">Solicitante</td>
            <td>{{ $requisicion->solicitante?->name ?? '-' }}</td>
        </tr>
    </table>

    <p class="legend">
        <span class="swatch sel"></span> Proveedor elegido para la orden de compra
    </p>

    <table class="comp-table">
        <thead>
            <tr>
                <th rowspan="2">Cantidad</th>
                <th rowspan="2">Descripcion</th>
                <th rowspan="2">Obra / Centro de costos</th>
                @foreach($grupos as $g)
                <th class="prov" colspan="{{ $g['opciones']->count() }}">
                    {{ $g['nombre'] }}
                </th>
                @endforeach
                <th rowspan="2">Importe</th>
            </tr>
            <tr>
                @foreach($grupos as $g)
                    @foreach($g['opciones'] as $i => $op)
                    <th class="{{ $i === 0 ? 'prov' : '' }}">{{ $etiquetaOpcion($op) }}</th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($requisicion->detalles as $d)
            @php
                $info = $importeDetalle($d);
                $seleccionIds = $d->selecciones->pluck('cotizacion_precio_id')->map(fn ($v) => (int) $v)->all();
            @endphp
            <tr>
                <td class="text-right">{{ number_format($d->cantidad, 2) }} {{ $d->unidad }}</td>
                <td>{{ $d->descripcion }}</td>
                <td>
                    @if($d->obraRubro?->obra)
                        {{ $d->obraRubro->obra->no ? 'OP-'.$d->obraRubro->obra->no.' ' : '' }}{{ $d->obraRubro->obra->descripcion }}
                    @endif
                    @if($d->obraRubro?->rubro)
                        <br><span style="font-size: 8px; color: #666;">{{ $d->obraRubro->rubro->codigo }} - {{ $d->obraRubro->rubro->descripcion }}</span>
                    @endif
                </td>
                @foreach($grupos as $g)
                    @foreach($g['opciones'] as $i => $op)
                    @php
                        $cot = $cotizacionDe($d, $op->id);
                        $precio = $cot ? (float) $cot->precio_unitario : null;
                        $seleccionado = $cot && in_array((int) $cot->id, $seleccionIds, true);
                    @endphp
                    <td class="text-right {{ $i === 0 ? 'opcion' : '' }} {{ $seleccionado ? 'seleccionado' : '' }}">
                        @if($precio !== null)
                            ${{ number_format($precio, 2) }}
                            @if($cot->moneda && strtolower($cot->moneda) !== 'mxn')
                                <span style="font-size: 8px; color: #666;">{{ strtoupper($cot->moneda) }}</span>
                            @endif
                            @if($cot->descripcion)
                                <br><span style="font-size: 8px; color: #666;">{{ $cot->descripcion }}</span>
                            @endif
                            @if($cot->tiempo_entrega_dias)
                                <br><span style="font-size: 8px; color: #666;">{{ $cot->tiempo_entrega_dias }}d entrega</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    @endforeach
                @endforeach
                <td class="text-right">
                    @if($info['tiene'])
                        ${{ number_format($info['importe'], 2) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="{{ 3 + $numCols }}" class="text-right">SUBTOTAL</td>
                <td class="text-right">${{ number_format($subtotalComp, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="{{ 3 + $numCols }}" class="text-right">IVA (16%)</td>
                <td class="text-right">${{ number_format($ivaComp, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="{{ 3 + $numCols }}" class="text-right">TOTAL</td>
                <td class="text-right">${{ number_format($totalComp, 2) }}</td>
            </tr>
            <tr class="letras-row">
                <td colspan="{{ 4 + $numCols }}">{{ \App\Support\NumeroALetras::convertir((float) $totalComp, 'mxn') }}</td>
            </tr>
        </tfoot>
    </table>

    @if($requisicion->justificacion)
    <p style="font-size: 9px; margin-bottom: 15px;"><strong>Justificación:</strong> {{ $requisicion->justificacion }}</p>
    @endif

    @if($firmas->isNotEmpty())
    <table class="signatures-table">
        <tr>
            @foreach($firmas as $firma)
            <td>
                <div class="sig-placeholder"></div>
                <div class="sig-name">{{ $firma->aprobada ? $firma->aprobador?->name : 'Pendiente' }}</div>
                <div class="sig-role">{{ $firma->permiso->descripcion }} (Nivel {{ $firma->permiso->nivel }})</div>
                @if($firma->aprobada && $firma->fecha)
                <div class="sig-date">{{ $firma->fecha }}</div>
                @endif
            </td>
            @endforeach
        </tr>
    </table>
    @endif
</body>
</html>
