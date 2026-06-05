<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        .page {
            padding: 25px;
        }

        .header {
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
        }

        .company-info {
            font-size: 10px;
        }

        .divider {
            border-bottom: 1px solid #000;
            margin: 10px 0;
        }

        .title {
            background: #0a0a5e;
            color: #fff;
            padding: 6px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .right {
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        td {
            padding: 4px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            width: 20%;
        }

        .value {
            width: 30%;
        }

        .note {
            font-size: 8px;
            font-style: italic;
            margin: 10px 0;
        }

        .signatures {
            margin-top: 40px;
        }

        .signature-box {
            width: 30%;
            display: inline-block;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 8px;
        }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>

<body>

    {{-- Barra de impresión (solo en navegador) --}}
    <div class="no-print" style="background:#0a0a5e;color:white;padding:8px 20px;display:flex;justify-content:space-between;align-items:center;font-family:Arial,sans-serif;font-size:12px;">
        <span><strong>Vista previa</strong> — {{ $permiso->folio }}</span>
        <button onclick="window.print()" style="background:white;color:#0a0a5e;border:none;padding:5px 14px;border-radius:4px;font-weight:700;cursor:pointer;">
            Imprimir / Guardar PDF
        </button>
    </div>

    <div class="page">

        {{-- ENCABEZADO --}}
        <div class="header">
            <table width="100%">
                <tr>
                    <td width="30%">
                        @include('pdf.partials.logo', ['width' => 250])
                    </td>

                    <td width="80%">
                        <div class="company-name">{{ $company['name'] }}</div>
                        <div class="company-info">{{ $company['address'] }}</div>
                        <div class="company-info">{{ $company['phone'] }}</div>
                        <div class="company-info">{{ $company['rfc'] }}</div>
                        <div class="company-info">{{ $company['website'] }}</div>
                        <div class="company-info"><strong>SOLICITUD DE PERMISO LABORAL</strong></div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="divider"></div>

        {{-- FOLIO --}}
        <div class="right">
            <strong>Folio:</strong> {{ $permiso->folio }}<br>
            <strong>Fecha y hora de impresión:</strong> {{ now()->format('d/m/Y H:i:s') }}
        </div>

        {{-- INFORMACIÓN --}}
        <div class="title">INFORMACIÓN DEL PERMISO</div>

        <table>
            <tr>
                <td class="label">Nombre del empleado:</td>
                <td class="value">{{ $permiso->nombres }} {{ $permiso->apellidos }}</td>
                <td class="label">Número de empleado:</td>
                <td class="value">{{ $permiso->numero_empleado }}</td>
            </tr>
            <tr>
                <td class="label">Departamento:</td>
                <td class="value">{{ $permiso->departamento }}</td>
                <td class="label">Gerente del departamento:</td>
                <td class="value">{{ $permiso->gerente }}</td>
            </tr>
            <tr>
                <td class="label">Tipo de permiso solicitado:</td>
                <td class="value">{{ $permiso->condicion }}</td>
            </tr>
            <tr>
                <td class="label">Fecha del permiso:</td>
                <td class="value">{{ $permiso->fecha_permiso?->format('d/m/Y H:i') ?? '—' }} | {{ $permiso->modalidad }}</td>
            </tr>
            <tr>
                <td class="label">Condición:</td>
                <td class="value">{{ $permiso->tipo }}</td>
            </tr>
            <tr>
                <td class="label">Motivo:</td>
                <td class="value" colspan="3">{{ $permiso->razon }}</td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Fecha elaboración:</td>
                <td class="value">{{ $permiso->fecha_elaboracion?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        </table>

        <div class="note">
            Las solicitudes de permiso laboral se deben presentar dos días antes del primer día en que se estará
            ausente, a excepción del permiso por enfermedad o fallecimiento.
        </div>

        {{-- FIRMAS --}}
        <div class="title">FIRMAS DEL PERMISO</div>

        <div class="signatures">
            <div class="signature-box">
                {{ $permiso->nombres }} {{ $permiso->apellidos }}<br>
                Trabajador
            </div>

            <div class="signature-box">
                Nombre: ___________________________<br>
                Jefe inmediato
            </div>

            <div class="signature-box">
                Nombre: ___________________________<br>
                Gerente / Subgerente
            </div>
        </div>

    </div>

    <div class="footer">1 / 1</div>

</body>

</html>
