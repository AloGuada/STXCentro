<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato Laboral - {{ $persona->nombre }} {{ $persona->apellido }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.6;
            padding: 60px 75px;
        }
        .page-break {
            page-break-before: always;
        }
        .text-center {
            text-align: center;
        }
        .text-justify {
            text-align: justify;
        }
        .text-right {
            text-align: right;
        }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mb-8 { margin-bottom: 32px; }
        .mt-4 { margin-top: 16px; }
        .mt-8 { margin-top: 32px; }
        .mt-16 { margin-top: 64px; }
        .underline { text-decoration: underline; }
        .bold { font-weight: bold; }

        /* Header con foto */
        .header-row {
            width: 100%;
            margin-bottom: 30px;
        }
        .header-row td {
            vertical-align: middle;
        }
        .photo-cell {
            width: 120px;
        }
        .photo-placeholder {
            width: 110px;
            height: 130px;
            border: 1px solid #999;
            text-align: center;
            line-height: 130px;
            color: #999;
            font-size: 10px;
        }
        .info-cell {
            text-align: center;
            vertical-align: middle;
        }

        /* Datos generales */
        .data-table {
            width: 85%;
            margin: 0 auto;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #ccc;
            font-size: 11px;
        }
        .data-table .label {
            width: 50%;
            font-weight: normal;
        }
        .data-table .value {
            width: 50%;
        }

        /* Observaciones */
        .obs-box {
            border: 1px solid #000;
            padding: 10px;
            min-height: 60px;
            margin-bottom: 12px;
            width: 85%;
            margin-left: auto;
            margin-right: auto;
        }

        /* Formato de ingreso header */
        .logo-cell img {
            max-width: 80px;
        }
        .code-table {
            border-collapse: collapse;
            font-size: 10px;
        }
        .code-table td {
            border: 1px solid #000;
            padding: 3px 8px;
        }

        /* Formato ingreso datos */
        .ingreso-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ingreso-table td {
            padding: 3px 4px;
            font-size: 11px;
            vertical-align: top;
        }
        .ingreso-label {
            width: 40%;
        }
        .ingreso-value {
            border-bottom: 1px solid #000;
        }

        /* Documentos checklist */
        .docs-row {
            width: 100%;
        }
        .docs-row td {
            width: 33%;
            vertical-align: top;
            padding: 2px 8px;
            font-size: 11px;
        }

        /* Contrato legal */
        .contrato-text {
            font-size: 11px;
            text-align: justify;
            margin: 0 20px;
            line-height: 1.5;
        }
        .clausula {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

@if($tipoContrato !== 'obra')
{{-- ==================== PÁGINA 1: DATOS GENERALES ==================== --}}
<table class="header-row">
    <tr>
        <td class="photo-cell">
            @if($fotoPath)
                <img src="{{ $fotoPath }}" alt="Foto" style="width: 110px; height: 130px; object-fit: cover;">
            @else
                <div class="photo-placeholder">FOTO</div>
            @endif
        </td>
        <td class="info-cell">
            <p>No. de empleado: {{ $numeroEmpleado ?: '________________________' }}</p>
            <p>No. de locker: {{ $numeroLocker ?: '___________________________' }}</p>
        </td>
    </tr>
</table>

<p class="text-center bold mb-6">DATOS GENERALES</p>

<table class="data-table">
    <tr>
        <td class="label">No. IMSS DEL TRABAJADOR:</td>
        <td class="value">{{ $extras->imss ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">APELLIDOS:</td>
        <td class="value">{{ $persona->apellido }}</td>
    </tr>
    <tr>
        <td class="label">NOMBRES:</td>
        <td class="value">{{ $persona->nombre }}</td>
    </tr>
    <tr>
        <td class="label">C U R P:</td>
        <td class="value">{{ $extras->curp ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">R.F.C. CON HOMOCLAVE:</td>
        <td class="value">{{ $extras->rfc ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">CORREO ELECTRONICO:</td>
        <td class="value">{{ $persona->email ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">TELEFONO:</td>
        <td class="value">{{ $persona->telefono ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">FECHA DE INGRESO:</td>
        <td class="value">{{ $fechaIngreso }}</td>
    </tr>
    <tr>
        <td class="label">SEXO:</td>
        <td class="value">{{ $sexo }}</td>
    </tr>
    <tr>
        <td class="label">LUGAR DE NACIMIENTO:</td>
        <td class="value">{{ $lugarNacimiento }}</td>
    </tr>
    <tr>
        <td class="label">FECHA DE NACIMIENTO:</td>
        <td class="value">{{ $fechaNacimiento }}</td>
    </tr>
    <tr>
        <td class="label">NOMBRE DEL PAPA:</td>
        <td class="value">{{ $extras->nombre_padre ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">NOMBRE DE LA MAMA:</td>
        <td class="value">{{ $extras->nombre_madre ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">HIJOS:</td>
        <td class="value">{{ ($extras->hijos ?? 0) > 0 ? 'SI' : 'NO' }}</td>
    </tr>
    <tr>
        <td class="label">ESTADO CIVIL:</td>
        <td class="value">{{ $extras->estado_civil ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">DOMICILIO:</td>
        <td class="value">{{ $extras->domicilio ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">CODIGO POSTAL:</td>
        <td class="value">{{ $extras->cp ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">PUESTO:</td>
        <td class="value">{{ $puesto }}</td>
    </tr>
    <tr>
        <td class="label">CUENTA BANCO:</td>
        <td class="value">{{ $extras->cuenta_banco ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">DEPARTAMENTO:</td>
        <td class="value">{{ $departamento }}</td>
    </tr>
    <tr>
        <td class="label">SUELDO MENSUAL:</td>
        <td class="value">{{ $sueldoMensual }}</td>
    </tr>
</table>

{{-- ==================== PÁGINA 2: OBSERVACIONES ==================== --}}
<div class="page-break"></div>

<div class="obs-box">
    <p class="bold mb-2">OBSERVACIONES:</p>
    <p>&nbsp;</p>
</div>

<div class="obs-box">
    <p class="bold mb-2">CONTACTOS PARA EMERGENCIA:</p>
    @forelse($contactosEmergencia as $contacto)
        <p>{{ $contacto->nombre }}: {{ $contacto->telefono }}</p>
    @empty
        <p>_________________________________: _________________________________</p>
        <p>_________________________________: _________________________________</p>
    @endforelse
</div>

<table class="data-table mb-4">
    <tr>
        <td class="label">¿Tienes crédito de INFONAVIT?</td>
        <td class="value">{{ ($extras->c_infonavit ?? false) ? 'SI' : 'NO' }}</td>
    </tr>
    <tr>
        <td class="label">¿Tienes crédito de FONACOT?</td>
        <td class="value">{{ ($extras->c_fonacot ?? false) ? 'SI' : 'NO' }}</td>
    </tr>
</table>

<div class="text-center mt-16">
    <p class="mb-4">FIRMA DEL TRABAJADOR:</p>
    <p>______________________________________</p>
</div>

{{-- ==================== PÁGINA 3: FORMATO DE INGRESO (condicional) ==================== --}}
@if($tipoContrato != 'contratista')
<div class="page-break"></div>

<table style="width: 100%; margin-bottom: 20px;">
    <tr>
        <td style="width: 100px;">
            @include('pdf.partials.logo', ['width' => 80])
        </td>
        <td class="text-center">
            <span class="bold underline">Formato de ingreso para Contratistas</span>
        </td>
        <td style="width: 100px;"></td>
    </tr>
</table>

<div class="text-right mb-6">
    <table class="code-table" style="float: right;">
        <tr>
            <td>CÓDIGO</td>
            <td>F-STX-RH-02</td>
        </tr>
        <tr>
            <td>REVISION</td>
            <td>1</td>
        </tr>
        <tr>
            <td>FECHA DE APROBACIÓN</td>
            <td>01/08/2024</td>
        </tr>
    </table>
    <div style="clear: both;"></div>
</div>

<table class="ingreso-table mb-6">
    <tr>
        <td colspan="2" style="width: 55%;">
            <table style="width: 100%;">
                <tr><td class="ingreso-label">Apellido paterno:</td><td class="ingreso-value">{{ $persona->apellido }}</td></tr>
                <tr><td class="ingreso-label">Apellido materno:</td><td class="ingreso-value"></td></tr>
                <tr><td class="ingreso-label">Nombre:</td><td class="ingreso-value">{{ $persona->nombre }}</td></tr>
                <tr><td class="ingreso-label">Numero celular:</td><td class="ingreso-value">{{ $persona->telefono ?? '' }}</td></tr>
                <tr><td class="ingreso-label">Fecha de ingreso:</td><td class="ingreso-value">{{ $fechaIngreso }}</td></tr>
                <tr><td class="ingreso-label">Sueldo mensual:</td><td class="ingreso-value">{{ $sueldoMensual }}</td></tr>
                <tr><td class="ingreso-label">Salario diario:</td><td class="ingreso-value">{{ $salarioDiario }}</td></tr>
            </table>
        </td>
        <td style="width: 45%;">
            <table style="width: 100%;">
                <tr><td>No. de contratista:</td><td>{{ $numeroEmpleado ?: '___________' }}</td></tr>
                <tr><td>No. de Locker:</td><td>{{ $numeroLocker ?: '___________' }}</td></tr>
                <tr><td>Area:</td><td class="ingreso-value">{{ $departamento }}</td></tr>
                <tr><td>Módulo:</td><td>___________</td></tr>
                <tr><td>Contratista encargado:</td><td>___________</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="text-center mt-8">
    <p class="mb-2">________________________________________</p>
    <p class="mb-6">Firma y nombre del contratista</p>
    <p class="mb-2">________________________________________</p>
    <p class="mb-6">Firma y nombre del supervisor</p>
    <p class="mb-2">________________________________________</p>
    <p class="mb-6">Firma y nombre de Nóminas</p>
    <p class="mb-2">________________________________________</p>
    <p class="mb-6">Firma y nombre de planta</p>
</div>

<p class="bold mb-4" style="margin-left: 40px;">Documentos recolectados y entregados al departamento de nóminas:</p>
<p style="margin-left: 40px;">Copia de INE</p>

@elseif($tipoContrato === 'planta')
<div class="page-break"></div>

<table style="width: 100%; margin-bottom: 20px;">
    <tr>
        <td style="width: 100px;">
            @include('pdf.partials.logo', ['width' => 80])
        </td>
        <td class="text-center">
            <span class="bold underline">Formato de ingreso para empleados de Planta</span>
        </td>
        <td style="width: 100px;">
            @include('pdf.partials.logo', ['width' => 80])
        </td>
    </tr>
</table>

<div class="text-right mb-6">
    <table class="code-table" style="float: right;">
        <tr>
            <td>CÓDIGO</td>
            <td>F-STX-RH-02</td>
        </tr>
        <tr>
            <td>REVISION</td>
            <td>1</td>
        </tr>
        <tr>
            <td>FECHA DE APROBACIÓN</td>
            <td>01/08/2024</td>
        </tr>
    </table>
    <div style="clear: both;"></div>
</div>


<table style="width: 100%;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <table style="width: 100%;">
                <tr><td class="ingreso-label">Nombre completo:</td><td class="ingreso-value">{{ $persona->nombre }} {{ $persona->apellido }}</td></tr>
                <tr><td class="ingreso-label">Fecha de ingreso:</td><td class="ingreso-value">{{ $fechaIngreso }}</td></tr>
                <tr><td class="ingreso-label">Área:</td><td class="ingreso-value">{{ $departamento }}</td></tr>
                <tr><td class="ingreso-label">Puesto:</td><td class="ingreso-value">{{ $puesto }}</td></tr>
                <tr><td class="ingreso-label">Categoria:</td><td class="ingreso-value"></td></tr>
                <tr><td class="ingreso-label">Sueldo mensual:</td><td class="ingreso-value">{{ $sueldoMensual }}</td></tr>
                <tr><td class="ingreso-label">Salario diario:</td><td class="ingreso-value">{{ $salarioDiario }}</td></tr>
                <tr><td class="ingreso-label">Banco operador:</td><td class="ingreso-value">{{ $extras->banco_op ?? '' }}</td></tr>
                <tr><td class="ingreso-label">Correo:</td><td class="ingreso-value">{{ $persona->email ?? '' }}</td></tr>
            </table>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table style="width: 100%;">
                <tr><td>No. de empleado:</td><td>{{ $numeroEmpleado ?: '___________' }}</td></tr>
                <tr><td>No. de Locker:</td><td>{{ $numeroLocker ?: '___________' }}</td></tr>
                <tr><td>No. de mov. IMSS:</td><td>___________</td></tr>
                <tr><td>Linea de producción:</td><td>___________</td></tr>
                <tr><td>Infonavit:</td><td>{{ ($extras->c_infonavit ?? false) ? 'SI' : 'NO' }}</td></tr>
                <tr><td>Fonacot:</td><td>{{ ($extras->c_fonacot ?? false) ? 'SI' : 'NO' }}</td></tr>
                <tr><td>No. Cuenta:</td><td>{{ $extras->cuenta_banco ?? '' }}</td></tr>
                <tr><td>Telefono:</td><td>{{ $persona->telefono ?? '' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="text-center mt-8">
    <p class="mb-2">Firma y fecha de Capital Humano</p>
    <p class="mb-6">________________________________________</p>
    <table style="width: 100%; margin-bottom: 16px;">
        <tr>
            <td class="text-center" style="width: 50%;">
                <p class="mb-2">Firma y fecha de Gerente de Planta</p>
                <p>________________________________________</p>
            </td>
            <td class="text-center" style="width: 50%;">
                <p class="mb-2">Firma y fecha de Jefe de Área</p>
                <p>________________________________________</p>
            </td>
        </tr>
    </table>
    <p class="mb-2">Nombre y Firma de recibido del Responsable de nómina</p>
    <p class="mb-6">________________________________________</p>
</div>

<p class="bold mb-4" style="margin-left: 40px;">Documentos:</p>
<table class="docs-row">
    <tr>
        <td>
            <p>Curp</p>
            <p>Acta de nacimiento</p>
            <p>Seguro social</p>
            <p>Constancia de sit. (CSF.)</p>
        </td>
        <td>
            <p>INE</p>
            <p>Número de cuenta</p>
            <p>Cv/Solicitud de empleo</p>
        </td>
        <td>
            <p>Compr. domiciliario</p>
            <p>Contrato laboral</p>
            <p>Alta IMSS</p>
            <p>Otros:</p>
        </td>
    </tr>
</table>
@endif
@endif {{-- fin tipoContrato !== obra --}}

{{-- ==================== PÁGINA FINAL: CONTRATO LEGAL ==================== --}}
@if($tipoContrato !== 'obra')
<div class="page-break"></div>
@endif

@if($tipoContrato === 'obra')
{{-- ==================== CONTRATO POR OBRA DETERMINADA ==================== --}}
<div class="contrato-text">
    <p class="clausula">
        CONTRATO INDIVIDUAL DE TRABAJO POR TIEMPO Y OBRA DETERMINADOS QUE CELEBRAN:
        LA EMPRESA <span class="underline">TIM DEL MAYAB SA DE CV</span>. DE C. V. REPRESENTADA EN ESTE ACTO POR SU REPRESENTANTE LEGAL, SR. <span class="underline">ALEJANDRO GASQUE MIER Y TERAN</span> Y COMO TRABAJADOR EL C. <span class="underline">{{ mb_strtoupper($persona->nombre) }} {{ mb_strtoupper($persona->apellido) }}</span> SUJETANDOSE PARA TODOS LOS EFECTOS LEGALES, AL TENOR DE LAS SIGUIENTES DECLARACIONES Y CLAUSULAS.
    </p>

    <p class="text-center bold mb-4">DECLARACIONES</p>

    <p class="clausula">
        PRIMERA.- EL SR. ALEJANDRO GASQUE MIER Y TERAN CON LA PERSONALIDAD QUE OSTENTA DECLARA: PARA LOS EFECTOS DEL ARTICULO 25 DE LA LEY FEDERAL DEL TRABAJO, QUE LA NEGOCIACIÓN QUE REPRESENTA SE DEDICA A FABRICACION Y MONTAJE DE ESTRUCTURAS METALICAS CON DOMICILIO EN CALLE-S/N COL. XEPLAC EN KANASIN, YUCATAN, EMPRESA A QUIEN SE NOMBRA EN LO SUCESIVO COMO EL PATRON.
    </p>

    <p class="clausula">
        SEGUNDA.- POR SU PARTE EL C. <span class="underline">{{ mb_strtoupper($persona->nombre) }} {{ mb_strtoupper($persona->apellido) }}</span> MANIFIESTA: SER MEXICANO, MAYOR DE EDAD LEGAL, CON DOMICILIO EN EL PREDIO <span class="underline">{{ $extras->domicilio ?? '' }} C.P. {{ $extras->cp ?? '' }}</span> INE NÚMERO <span class="underline">{{ $extras->numero_ine ?? '' }}</span> , ESTAR ENTERADO DE LA ACTIVIDAD DE LA EMPRESA Y QUE TIENE LAS APTITUDES, LOS CONOCIMIENTOS Y LA EXPERIENCIA PROPIOS Y NECESARIOS PARA LA EJECUCIÓN, REALIZACIÓN Y DESEMPEÑO DEL TRABAJO Y LAS LABORES QUE LE ENCOMIENDAN Y A QUIEN SE NOMBRA EN LO SUCESIVO COMO EL TRABAJADOR.
    </p>

    <p class="clausula">
        TERCERA.- AMBAS PARTES CONTRATANTES ACUERDAN: QUE EL PRESENTE CONTRATO SE CELEBRA POR OBRA Y TIEMPO DETERMINADO CON UNA VIGENCIA DE (TRES MESES) Y/O CONCLUYA LA OBRA, LO QUE OCURRA PRIMERO Y COMENZARA A SER APLICABLE A PARTIR DE LA FECHA DE SU SUSCRIPCIÓN, RECONOCIENDO EXPRESAMENTE EL TRABAJADOR QUE LA NATURALEZA DEL TRABAJO ES COMPLETAMENTE TEMPORAL TAL Y COMO SE HACE CONSTAR EN EL PRESENTE CONTRATO.
    </p>

    <p class="text-center bold mb-4">C L A U S U L A S</p>

    <p class="clausula">
        PRIMERA.- EL TRABAJADOR SE OBLIGA Y COMPROMETE A PRESTAR SERVICIOS PERSONALES SUBORDINADOS AL PATRON CON EL PUESTO DE "<span class="underline">{{ $puesto }}</span>" BAJO LA DIRECCIÓN Y DEPENDENCIA DE LOS REPRESENTANTES LEGALES DE LA PERSONA MORAL DE LA RELACIÓN DE TRABAJO, EN EL DOMICILIO DE LA NEGOCIACIÓN DEL MISMO Y/O EN EL LUGAR QUE SE LE INDIQUE PARA ELLO; Y POR CUANTO MANIFIESTA TENER LOS CONOCIMIENTOS Y LAS APTITUDES PARA DESEMPEÑAR EL PUESTO ANTES REFERIDO, POR EL TÉRMINO DE (TRES MESES) Y/O EL TÉRMINO DE CONCLUSIÓN DE LA OBRA DETERMINADA CONSISTENTE EN _________________________ UBICADA EN _________________________ DANDOSE POR ENTERADO Y POR TANTO OTORGA SU ANUENCIA DE LA MATERIA DEL PRESENTE CONTRATO YA QUE CONCLUIDO DICHO TÉRMINO, Y/O LA OBRA DESCRITA, LO QUE OCURRA PRIMERO YA QUE DICHO TÉRMINO ES EL APROXIMADO PARA LA CONCLUSIÓN DE LA MISMA, CONCLUIRÁ EL PRESENTE CONTRATO SIN RESPONSABILIDAD ALGUNA PARA NINGUNA DE LAS PARTES.
    </p>

    <p class="clausula">
        SEGUNDA.- LAS LABORES QUE DESEMPEÑARA EL TRABAJADOR, CONSISTIRAN EN: "<span class="underline">{{ $puesto }}</span>"; ASI COMO CUALQUIER OTRA ACTIVIDAD INHERENTE A DICHO PUESTO DE TRABAJO.
    </p>

    <p class="clausula">
        TERCERA.- LAS PARTES CONVIENEN QUE LA JORNADA DE TRABAJO SERA DE CUARENTA Y OCHO HORAS SEMANALES, QUEDANDO DISTRIBUIDAS DE ACUERDO AL SIGUIENTE HORARIO DE TRABAJO: DE LUNES A VIERNES DE 8:00 A 17:00 HRS Y LOS SABADOS DE 8:00 A 11:00, SIENDO EL DOMINGO EL DÍA DE DESCANSO SEMANAL.
    </p>

    <p class="clausula">
        CUARTA.- PATRON Y TRABAJADOR CONVIENEN Y SE COMPROMETEN A NO LABORAR HORAS EXTRAS, NI DIAS DE DESCANSO SEMANAL ASI COMO TAMPOCO LOS DIAS DESCANSO OBLIGATORIO LEGALMENTE ESTABLECIDOS Y UNICAMENTE PODRA HACERSE CUANDO EL TRABAJADOR RECIBA DEL PATRON O DE SU REPRESENTANTE LEGAL, LA ORDEN O AUTORIZACION POR ESCRITO PARA ELLO.
    </p>

    <p class="clausula">
        QUINTA.- EL MONTO DEL SALARIO DIARIO DEL TRABAJADOR SE HARA CONSTAR EN EL RECIBO DE PAGO CORRESPONDIENTE, PAGADERO SEMANALMENTE Y OBLIGANDOSE EL PATRON A CUBRIR EL SALARIO ESTIPULADO LOS DIAS SABADO DE CADA SEMANA, EN MONEDA DE CURSO LEGAL, EN EL CENTRO DE TRABAJO, Y EL TRABAJADOR FIRMARA LA LISTA DE RAYA DE NOMINA.
    </p>

    <p class="clausula">
        SEXTA.- EL TRABAJADOR EXPRESA SU CONFORMIDAD Y AUTORIZA AL PATRÓN PARA QUE DEDUZCA DE SU SALARIO LOS IMPUESTOS QUE SEAN A SU CARGO, LAS CUOTAS OBRERAS DEL INSTITUTO MEXICANO DEL SEGURO SOCIAL, ASÍ COMO CUALQUIER OTRA CANTIDAD A CUYO PAGO PUDIERA ESTAR OBLIGADO EL TRABAJADOR, Y EN ESPECIAL AQUELLOS A QUE SE REFIEREN LOS ARTÍCULOS 97 Y 110 DE LA LEY FEDERAL DEL TRABAJO.
    </p>

    <p class="clausula">
        SEPTIMA.- LAS PARTES CONVIENEN QUE EL TRABAJADOR ESTARA OBLIGADO A REGISTRAR SU ASISTENCIA EN EL FORMATO DE LISTA DE ASISTENCIA DE LA EMPRESA, POR LO QUE EL INCUMPLIMIENTO DE ESTE REQUISITO INDICARA LA FALTA INJUSTIFICADA A SUS LABORES, PARA TODOS LOS EFECTOS LEGALES CORRESPONDIENTES.
    </p>

    <p class="clausula">
        OCTAVA.- LAS FALTAS AL TRABAJO POR PARTE DEL TRABAJADOR, SERAN JUSTIFICADAS UNICAMENTE CON LA EXHIBICION DEL CERTIFICADO DE INCAPACIDAD QUE AL EFECTO EXPIDE EL INSTITUTO MEXICANO DEL SEGURO SOCIAL; DE LO CONTRARIO SE CONSIDERA COMO INJUSTIFICADAS LAS FALTAS AL TRABAJO.
    </p>

    <p class="clausula">
        NOVENA.- EL PATRON OTORGARA Y PAGARA AL TRABAJADOR LOS PERIODOS VACACIONALES, PRIMA VACACIONAL, Y EL IMPORTE DE AGUINALDO ANUAL, EN LA FORMA, TERMINOS Y CONDICIONES PREVISTAS POR LOS ARTICULOS 76 Y 87 DE LA LEY FEDERAL DEL TRABAJO EN VIGOR MISMAS QUE SE CONVIENE SEA PAGADAS EN FORMA PROPORCIONAL SEMANAL AL SER PAGADO EL SALARIO.
    </p>

    <p class="clausula">
        DECIMA.- PATRON Y TRABAJADOR DECLARAN: QUE SE RECONOCEN MUTUAMENTE NO TENER UNA ANTIGÜEDAD EN SU RELACION LABORAL ANTERIORES A LA FIRMA DEL PRESENTE CONTRATO.
    </p>

    <p class="clausula">
        DECIMA PRIMERA.- PATRON Y TRABAJADOR ACUERDAN QUE CON RESPECTO A SUS DERECHOS Y OBLIGACIONES QUE MUTUAMENTE LES CORRESPONDEN Y QUE TODO LO NO PREVISTO Y QUE NO HAYA SIDO OBJETO DE CLAUSULA ESPECIAL EN EL PRESENTE CONTRATO, SE SUJETAN A LAS DISPOSICIONES DE LA LEY FEDERAL DE TRABAJO EN VIGOR INCLUIDAS LAS NORMAS DE CAPACITACIÒN, SEGURIDAD E HIGIENE.
    </p>

    <p class="clausula">
        LEIDO QUE FUE EL PRESENTE CONTRATO POR LAS PARTES, E IMPUESTAS DE SU CONTENIDO Y FUERZA LEGAL, LO FIRMARON QUEDANDO UN TANTO EN PODER DE CADA UNA DE LAS PARTES EN LA CIUDAD DE MERIDA, YUCATAN A <span class="underline">{{ $fechaIngresoLarga }}</span>
    </p>

    <table style="width: 100%; margin-top: 60px;">
        <tr>
            <td class="text-center" style="width: 50%;">
                <p>POR EL PATRON</p>
                <p class="mt-16">______________________________________</p>
            </td>
            <td class="text-center" style="width: 50%;">
                <p>EL TRABAJADOR</p>
                <p class="mt-16">______________________________________</p>
            </td>
        </tr>
    </table>
</div>

@else
{{-- ==================== CONTRATO POR TIEMPO DETERMINADO (PLANTA) ==================== --}}
<p class="text-right mb-6">
    MERIDA, YUC. <span class="underline">A {{ $fechaIngresoLarga }}</span>
</p>

<div class="contrato-text">
    <p class="clausula">
        CONTRATO INDIVIDUAL DE TRABAJO POR <span class="underline">TIEMPO DETERMINADO</span> QUE CELEBRAN
        DE UNA PARTE <span class="underline">TIM DEL MAYAB, S. A. DE C.V.,</span>
        REPRESENTADA POR, <span class="underline">ALEJANDRO GASQUE MIER Y TERAN</span> A
        QUIEN EN LO SUCESIVO SE LE DESIGNARA COMO EL PATRON Y DE LA OTRA PARTE
        <span class="underline">{{ mb_strtoupper($persona->nombre) }} {{ mb_strtoupper($persona->apellido) }}</span>
        A QUIEN EN ADELANTE SE LE DENOMINARA COMO EL TRABAJADOR, HACEMOS
        CONSTAR QUE EL CONTRATO DE TRABAJO QUE HEMOS CELEBRADO Y QUE SUJETAMOS
        AL TENOR DE LAS ESTIPULACIONES QUE SE CONSIGNAN EN LAS SIGUIENTES:
    </p>

    <p class="text-center bold mb-4">CLAUSULAS:</p>

    <p class="clausula">
        PRIMERA: PARA LOS EFECTOS DEL ARTICULO 25 DE LA LEY FEDERAL DEL
        TRABAJO, EL SEÑOR: <span class="underline">ALEJANDRO GASQUE MIER Y TERAN,</span>
        DECLARA QUE SU REPRESENTADA ES UNA SOCIEDAD MEXICANA, DEDICADA
        <span class="underline">A FABRICACION Y MONTAJE DE ESTRUCTURAS METALICAS</span>,
        CON DOMICILIO EN EL PREDIO NUMERO <span class="underline"> S/N </span> DE LA CALLE:
        <span class="underline"> MERIDA-PETO KM 1 SKY PARK </span> DEL
        CENTRO., DE LA CIUDAD __KANASIN___ Y ACREDITA SU PERSONALIDAD CON EL
        TESTIMONIO DE ESCRITURA PUBLICA NUMERO _____DE FECHA ____DE ____DEL
        AÑO DE ____OTORGADA EN ESTA CIUDAD DE MERIDA, YUCATAN, MEXICO, ANTE
        LA FE DEL NOTARIO PUBLICO ABOGADO _________TITULAR DE LA NOTARIA
        PUBLICA NUMERO _____ EL TRABAJADOR DECLARA LLAMARSE
        <span class="underline">{{ $persona->nombre }} {{ $persona->apellido }}, {{ $edad }} AÑOS</span>
        DE EDAD, DE NACIONALIDAD <span class="underline">MEXICANA</span> ESTADO CIVIL
        <span class="underline">{{ $extras->estado_civil ?? '' }}</span>
        Y CON DOMICILIO <span class="underline">{{ $extras->domicilio ?? '' }} C.P. {{ $extras->cp ?? '' }}</span>
        DE LA LOCALIDAD DE {{ $extras->localidad ?? '' }}.
    </p>

    <p class="clausula">
        SEGUNDA: EL TRABAJADOR SE OBLIGA A PRESTAR SUS SERVICIOS PROFESIONALES
        A <span class="underline">TIM DEL MAYAB, S.A. DE C.V.</span>
        SUBORDINADO JURIDICAMENTE AL PATRON, CONSISTENTES EN
        <span class="underline">{{ $puesto }}</span> ESTE
        TRABAJO DEBERA DE EJECUTARLO CON ESMERO Y EFICIENCIA, QUEDA
        EXPRESAMENTE CONVENIDO QUE ACATARA EN EL DESEMPEÑO DE SU TRABAJO TODAS
        LAS DISPOSICIONES DEL REGLAMENTO INTERIOR DE TRABAJO, TODAS LAS
        ORDENES, CIRCULARES Y DISPOSICIONES, QUE DICTE EL PATRON, Y TODOS LOS
        ORDENAMIENTOS LEGALES QUE LE SEAN APLICABLES.
    </p>

    <p class="clausula">
        TERCERA: EL TRABAJADOR DEBERA EJECUTAR SU TRABAJO EN
        <span class="underline">{{ $puesto }}</span> Y EN
        CUALQUIER LUGAR O ESTADO DE LA REPUBLICA MEXICANA, DONDE EL PATRON
        DESEMPEÑE ACTIVIDADES.
    </p>

    <p class="clausula">
        CUARTA: ESTE CONTRATO SE CELEBRA POR TIEMPO DETERMINADO POR UN PLAZO
        DE <span class="underline">90 DIAS</span> CONTADOS A PARTIR DE
        <span class="underline">HOY {{ $fechaIngresoLarga }}</span>
        QUE VENCERA POR CONSIGUIENTE AL EXPIRAR EL DIA
        <span class="underline">{{ $fechaVencimiento }}</span>
        FECHA EN LA QUE SE DARA POR TERMINADAS LAS RELACIONES LABORALES ENTRE
        AMBAS PARTES SIN RESPONSABILIDAD PARA NINGUNA DE ELLAS, QUEDANDO
        OBLIGADO EL PATRON A PAGARLE UNICAMENTE AL TRABAJADOR LOS SUELDOS O
        SALARIOS QUE HAYA DEVENGADO, ASI COMO LA PARTE PROPORCIONAL, EN SU
        CASO DE VACACIONES, PRIMA VACACIONAL Y AGUINALDO O CUALQUIER OTRO
        DERECHO ADQUIRIDO QUE SE GENERE DURANTE LA VIGENCIA DE ESTE CONTRATO,
        EN EL ENTENDIDO DE QUE LA TEMPORALIDAD QUE SE PACTA EN ESTE CONTRATO
        OBEDECE A QUE EL PATRON DURANTE LOS MESES DE
        _______________________________________________, TENDRA UN INCREMENTO
        EN LA PRODUCCION DADOS LOS PEDIDOS EXTRAORDINARIOS SOLICITADOS POR LOS
        CLIENTES DE ESTE, Y EN VIRTUD DE LA BAJA DE LA TEMPERATURA QUE SE
        PRESENTA DURANTE ESTE PERIODO.
    </p>

    <p class="clausula">
        QUINTA: EL TRABAJADOR PERCIBIRA POR LA PRESTACION DE LOS SERVICIOS A
        QUE SE REFIERE ESTE CONTRATO UN SALARIO DIARIO DE
        <span class="underline">$ {{ $salarioDiario }}</span>
        QUE SERA PAGADO AL TRABAJADOR EN FORMA ______________, AL CUAL SE LE
        APLICARA LA PARTE PROPORCIONAL CORRESPONDIENTE AL DESCANSO SEMANAL,
        CONFORME A LO DISPUESTOS EN EL ARTICULO 72 DE LA LEY FEDERAL DE
        TRABAJO, EL SALARIO SE LE CUBRIRA A TRAVES DE UN DEPOSITO A SU CUENTA
        DE NOMINA, LA CUAL LA EMPRESA LE APERTURARA AL MOMENTO DE LA FIRMA EL
        PRESENTE CONTRATO Y ANTE UNA INSTITUCION FINANCIERA DEBIDAMENTE
        REGISTRADA Y QUE PRESTE DICHO SERVICIO, ESTANDO OBLIGADO EL TRABAJADOR
        A FIRMAR LAS CONSTANCIAS DE PAGO RESPECTIVAS TENIENDO EN CUENTA LO
        DISPUESTOS EN LOS ARTICULOS 108 Y 109 DE DICHA LEY, EN EL ENTENDIDO DE
        QUE LA FIRMA DEL RECIBO CORRESPONDIENTE SERAN PRUEBA PLENA DE QUE EL
        TRABAJADOR ESTA DE ACUERDO CON DICHO PAGO, POR LO QUE NO SERA
        PROCEDENTE RECLAMACION DE NINGUNA ESPECIE DESPUES DE FIRMAR LOS
        RECIBOS DE PAGO DE SALARIO.
    </p>

    <p class="clausula">
        SEXTA: LA DURACION DE LA JORNADA DE TRABAJO SERA DE
        <span class="underline">10:00</span>
        HORAS DIARIAS, DE CADA SEMANA Y CON EL SIGUIENTE HORARIO DE LAS
        <span class="underline">08:00 A.M.</span> A LAS
        <span class="underline">18:00 P.M.</span> HORAS.
        GOZANDO DE 30 MINUTOS DIARIOS PARA TOMAR SUS ALIMENTOS, QUE SERA
        INVARIABLEMENTE DE LAS ___________________ A LAS ___________________
        HORAS. PUDIENDO SALIR DE LA FUENTE DE TRABAJO PARA DICHO EFECTO; POR
        LO QUE CONSTITUYE UNA JORNADA SEMANARIA DE
        <span class="underline">50</span> HORAS.
    </p>

    <p class="clausula">
        SEPTIMA: CUANDO POR CIRCUNSTACIAS EXTRAORDINARIAS, SE AUMENTE LA
        JORNADA DE TRABAJO, LOS SERVICIOS PRESTADOS DURANTE EL TIEMPO
        EXCEDENTE SE CONSIDERARA COMO EXTRAORDINARIOS Y SE PAGARAN A RAZON DEL
        CIENTO POR CIENTO MAS DEL SALARIO ESTABLECIDO PARA LAS HORAS DE
        TRABAJO NORMAL, TALES SERVICIOS NUNCA PODRAN EXCEDER DE TRES HORAS
        DIARIAS NI DE TRES VECES EN UNA SEMANA; EN LA INTELIGENCIA DE QUE EL
        TRABAJADOR NO ESTA AUTORIZADO PARA LABORAR EN TIEMPO EXTRAORDINARIO,
        SALVO QUE HAYA ORDEN EXPRESA Y POR ESCRITO, DEL REPRESENTANTE DEL
        PATRON.
    </p>

    <p class="clausula">
        OCTAVA: EL TRABAJADOR ESTA OBLIGADO A CHECAR SU TARJETA O FIRMAR LAS
        LISTAS DE ASISTENCIA, A LA ENTRADA Y SALIDA DE SUS LABORES, POR LO QUE
        EL INCUMPLIMIENTO DE ESE REQUISITO, INDICARA LA FALTA INJUSTIFICADA A
        SUS LABORES PARA TODOS LOS EFECTOS LEGALES.
    </p>

    <p class="clausula">
        NOVENA: POR CADA SEIS DIAS DE TRABAJO, TENDRA EL TRABAJADOR DESCANSO
        SEMANAL, DE UN DIA, CON PAGO DE SALARIO INTEGRO CONVINIÉNDOSE EN QUE
        DICHOS DESCANSOS LO DISFRUTARA LOS DIAS
        <span class="underline">DOMINGOS</span> DE CADA
        SEMANA TAMBIEN GOZARA DE DESCANSO, CON PAGO DE SALARIO INTEGRO LOS
        DIAS SEÑALADOS EN EL ARTICULO 74 DE LA LEY FEDERAL DE TRABAJO, A SABER
        EL PRIMERO DE ENERO, CINCO DE FEBRERO, VEINTIUNO DE MARZO, PRIMERO DE
        MAYO, DIECISEIS DE SEPTIEMBRE, VEINTE DE NOVIEMBRE, VEINTICINCO DE
        DICIEMBRE Y EL PRIMERO DE DICIEMBRE DE CADA SEIS AÑOS CUANDO
        CORRESPONDA A LA TRANSMISION DEL PODER EJECUTIVO FEDERAL.
    </p>

    <p class="clausula">
        DECIMA: EL TRABAJADOR PERCIBIRA POR CONCEPTO DE VACACIONES UNA REMUNERACION
        PROPORCIONAL AL TIEMPO DE SERVICIOS PRESTADOS CON UNA PRIMA DEL VEINTICINCO
        POR CIENTO SOBRE LOS SALARIOS CORRESPONDIENTES A LAS MISMAS, TENIENDO EN
        CUENTA EL TERMINO DE LA RELACION DE TRABAJO, CON ARREGLO A LO DISPUESTOS EN
        LOS ARTICULOS 76, 79 Y 80 DE LA LEY FEDERAL DE TRABAJO.
    </p>

    <p class="clausula">
        TAMBIEN PERCIBIRA CON BASE EN UN AGUINALDO ANUAL FIJADO EN EL EQUIVALENTE A
        QUINCE DIAS DE SALARIO, LA PARTE PROPORCIONAL AL TIEMPO TRABAJADO CONFORME AL
        PARRAFO SEGUNDO DEL ARTICULO 87 DE DICHA LEY.
    </p>

    <p class="clausula">
        DECIMA PRIMERA: EL TRABAJADOR CONVIENE EN SOMETERSE A LOS RECONOCIMIENTOS
        MEDICOS QUE PERIODICAMENTE ORDENE EL PATRON EN LOS TERMINOS DE LA FRACCION X
        DEL ARTICULO 134 DE LA LEY FEDERAL DE TRABAJO. EN LA INTELIGENCIA DE QUE EL
        MEDICO QUE LOS PRACTIQUE SERA DESIGNADO Y RETRIBUIDO POR EL PATRON.
    </p>

    <p class="clausula">
        DECIMA SEGUNDA: EL TRABAJADOR SERA CAPACITADO O ADIESTRADO EN LOS TERMINOS DE
        LOS PLANES Y PROGRAMAS ESTABLECIDOS (O QUE SE ESTABLEZCAN) POR EL PATRON,
        CONFORME A LO DISPUESTOS POR EL CAPITULO III BIS, TITULO CUARTO DE LA LEY
        FEDERAL DE TRABAJO.
    </p>

    <p class="clausula">
        DECIMA TERCERA: LAS PARTES CONVIENEN EN QUE TODO LO NO PREVISTO EN EL PRESENTE
        CONTRATO SE REGIRA POR LO DISPUESTOS EN LA LEY FEDERAL DEL TRABAJO, Y EN QUE
        PARA TODO LO QUE SE REFIERE A INTERPRETACION, EJECUCION Y CUMPLIMIENTO DEL
        MISMO, SE SOMETE EXPRESAMENTE A LA JURISDICCION Y COMPETENCIA DE LA JUNTA LOCAL
        DE CONCILIACION Y ARBITRAJE DEL ESTADO DE YUCATAN, ESTADOS UNIDOS MEXICANOS.
    </p>

    <p class="clausula">
        LEIDO EL PRESENTE CONTRATO POR AMBAS PARTES, E IMPUESTAS DE SU CONTENIDO Y
        FUERZA LEGAL, LO FIRMARON, QUEDANDO UN TANTO EN PODER DE CADA UNA DE LAS MISMAS.
    </p>

    <p class="text-center mt-8">
        <span class="underline">ALEJANDRO GASQUE MIER Y TERAN</span>
    </p>
    <p class="text-center">EL PATRON</p>

    <p class="text-center mt-16">EL TRABAJADOR</p>
</div>
@endif

</body>
</html>
