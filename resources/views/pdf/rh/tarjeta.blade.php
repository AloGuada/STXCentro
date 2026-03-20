<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tarjeta - {{ $persona->nombre }} {{ $persona->apellido }}</title>
    <style>
        @page {
            size: 85.6mm 54mm;
            margin: 3mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        /* === FRENTE === */
        .logo {
            height: 10mm;
        }

        .logo-text {
            font-size: 14px;
            font-weight: bold;
            color: #b91c1c;
            margin-bottom: 1mm;
        }

        .body-table {
            width: 100%;
            margin-top: 1mm;
        }

        .body-table td {
            vertical-align: middle;
            width: 50%;
        }

        .info {
            text-align: center;
        }

        .numero {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }

        .nombre {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            line-height: 1.3;
        }

        .puesto-label {
            font-size: 7px;
            color: #555;
            margin-top: 1mm;
            font-weight: bold;
            text-transform: uppercase;
        }

        .qr-section {
            text-align: center;
        }

        .qr-section img {
            width: 20mm;
            height: 20mm;
        }

        /* === REVERSO === */
        .back {
            page-break-before: always;
        }

        .mission {
            font-size: 6px;
            text-align: justify;
            line-height: 1.4;
            color: #333;
        }

        .contact {
            text-align: center;
            font-size: 8px;
            color: #555;
            margin-top: 2mm;
        }

        .company {
            text-align: center;
            font-size: 7px;
            font-weight: bold;
            color: #b91c1c;
            margin-top: 1mm;
        }
    </style>
</head>
<body>
    {{-- FRENTE --}}
    <div class="front">
        <img class="logo" src="{{ asset('logo_small.png') }}" alt="Steelex">

        <table class="body-table">
            <tr>
                <td>
                    <div class="info">
                        <div class="numero">{{ $numero }}</div>
                        <div class="nombre">{{ mb_strtoupper($persona->nombre) }}</div>
                        <div class="nombre">{{ mb_strtoupper($persona->apellido) }}</div>
                        @if ($puesto)
                            <div class="puesto-label">{{ $puesto }}</div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="qr-section">
                        <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="QR">
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- REVERSO --}}
    <div class="back">
        <p class="mission">
            STEELEX, empresa l&iacute;der en el Sureste Mexicano con proyecci&oacute;n
            Nacional e Internacional dedicada a las SOLUCIONES INTEGRALES
            en fabricaci&oacute;n de estructuras met&aacute;licas, se compromete a
            contar con personal calificado, infraestructura de tecnolog&iacute;a
            de punta y procesos semiautomatizados y/o automatizados,
            cumpliendo con los requisitos legales y normativos de nuestro
            sector, clientes y productos; cumpliendo continuamente los
            tiempos comprometidos de obra para satisfacer las necesidades
            de nuestros clientes y partes interesadas.
        </p>

        @if ($telefono)
            <div class="contact">{{ $telefono }}</div>
        @endif

        <div class="company">www.steelex.com.mx</div>
    </div>
</body>
</html>
