{{--
    El armazón común de los formatos de Almacén: los estilos, el encabezado con
    el logo y los datos fiscales, y el bloque del folio con su código de barras.

    Salió de `formato-salida`, que era el único formato que existía. Al aparecer
    los otros cuatro, tenerlo cinco veces copiado garantizaba que se separaran:
    un cambio de dirección fiscal habría que hacerlo en cinco lugares y se haría
    en tres.

    Uso:
        <x-pdf.formato-alm titulo="PEDIDO" subtitulo="SOLICITUD DE MATERIAL"
                           :folio="$pedido->folio">
            ... el cuerpo del documento ...
        </x-pdf.formato-alm>
--}}
@props(['titulo', 'subtitulo', 'folio'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} {{ $folio }}</title>
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

        /* Firmas. El margen superior las despega del detalle: una raya pegada a
           la tabla se lee como parte de ella. */
        .firmas-table { width: 100%; margin-top: 42px; }
        .firmas-table td { text-align: center; font-size: 9.5px; padding: 0 24px; vertical-align: bottom; }
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
                <div class="code-title">{{ $titulo }}</div>
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

    <div class="title">{{ $subtitulo }}</div>

    {{ $slot }}
</body>
</html>
