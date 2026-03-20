<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gafete - {{ $persona->nombre }} {{ $persona->apellido }}</title>
    <style>
        @page {
            size: 74mm 105mm;
            margin: 4mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            color: #1a1a1a;
        }

        /* === FRENTE === */
        .front {
            text-align: center;
        }

        .foto-container {
            width: 36mm;
            height: 36mm;
            margin: 0 auto 2mm;
            border: 1px solid #ccc;
            background: #e5e5e5;
            overflow: hidden;
        }

        .foto-container img {
            width: 36mm;
            height: 36mm;
        }

        .foto-placeholder {
            text-align: center;
            padding-top: 8mm;
            color: #999;
            font-size: 24px;
        }

        .logo {
            height: 8mm;
            margin-bottom: 2mm;
        }

        .logo-text {
            font-size: 16px;
            font-weight: bold;
            color: #b91c1c;
            margin-bottom: 2mm;
        }

        .numero {
            font-size: 12px;
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }

        .nombre {
            font-size: 11px;
            font-weight: bold;
            color: #000;
            line-height: 1.4;
        }

        .puesto-label {
            font-size: 8px;
            color: #555;
            margin-top: 2mm;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* === REVERSO === */
        .back {
            page-break-before: always;
            text-align: center;
        }

        .mission {
            font-size: 6.5px;
            text-align: justify;
            line-height: 1.5;
            color: #333;
            margin-bottom: 2mm;
        }

        .qr-section {
            text-align: center;
            margin: 2mm 0;
        }

        .qr-section img {
            width: 26mm;
            height: 26mm;
        }

        .contact {
            text-align: center;
            font-size: 9px;
            color: #555;
            margin-top: 1mm;
        }

        .company {
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            color: #b91c1c;
            margin-top: 1mm;
        }
    </style>
</head>
<body>
    {{-- FRENTE --}}
    <div class="front">
        <div class="foto-container">
            @if ($fotoPath)
                <img src="{{ $fotoPath }}" alt="Foto">
            @else
                <div class="foto-placeholder">&#128100;</div>
            @endif
        </div>

        <img class="logo" src="{{ asset('logo_small.png') }}"alt="Steelex">

        <div class="numero">{{ $numero }}</div>
        <div class="nombre">{{ mb_strtoupper($persona->nombre) }}</div>
        <div class="nombre">{{ mb_strtoupper($persona->apellido) }}</div>
        @if ($puesto)
            <div class="puesto-label">{{ $puesto }}</div>
        @endif
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
            de nuestros clientes y partes interesadas comprometidos con
            la mejora continua de nuestro sistema de gesti&oacute;n de calidad.
        </p>

        <div class="qr-section">
            <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="QR">
        </div>

        @if ($telefono)
            <div class="contact">{{ $telefono }}</div>
        @endif

        <div class="company">www.steelex.com.mx</div>
    </div>
</body>
</html>
