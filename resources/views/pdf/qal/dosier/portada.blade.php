{{--
    Portada e índice del dosier de calidad. Ver App\Services\Qal\Dosier\GeneradorDeDosier.

    El índice lista todas las secciones, tengan o no documentos, con cuántos
    lleva cada una: una sección vacía también dice algo al cliente. Al pie se
    nombran los PDF que el motor de unión no pudo incluir.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dosier de calidad · {{ $obra }}</title>
    <style>
        @page { margin: 50pt 50pt 40pt 50pt; }
        body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 10pt; }
        .portada { text-align: center; padding-top: 100pt; }
        .portada .titulo { font-size: 26pt; font-weight: bold; letter-spacing: 1.5pt; margin-top: 40pt; }
        .portada .obra { font-size: 15pt; margin-top: 14pt; }
        table.datos { margin: 50pt auto 0; border-collapse: collapse; }
        table.datos td { padding: 5pt 12pt; border-bottom: 0.6pt solid #bbb; text-align: left; font-size: 10.5pt; }
        table.datos td.etiqueta { font-weight: bold; color: #444; }
        .indice { page-break-before: always; }
        .indice h2 { font-size: 14pt; letter-spacing: 1pt; border-bottom: 1.5pt solid #000; padding-bottom: 4pt; margin: 0 0 8pt; }
        table.secciones { width: 100%; border-collapse: collapse; }
        table.secciones td { padding: 3pt 4pt; border-bottom: 0.4pt solid #ddd; vertical-align: top; font-size: 9.5pt; }
        table.secciones tr.n1 td { font-weight: bold; padding-top: 6pt; }
        .numero { width: 56pt; }
        .cuenta { width: 150pt; text-align: right; color: #555; font-size: 8.5pt; white-space: nowrap; }
        .fuera { color: #b3261e; }
        .aviso { margin-top: 14pt; padding: 6pt 8pt; border: 0.8pt solid #e3a39c; background: #fdecea; font-size: 8.5pt; }
    </style>
</head>
<body>
    <div class="portada">
        @include('pdf.partials.logo', ['width' => 220])
        <div class="titulo">DOSIER DE CALIDAD</div>
        <div class="obra">{{ $obra }}</div>
        <table class="datos">
            @foreach ($datos as $etiqueta => $valor)
                <tr>
                    <td class="etiqueta">{{ $etiqueta }}</td>
                    <td>{{ $valor }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="indice">
        <h2>ÍNDICE</h2>
        <table class="secciones">
            @foreach ($indice as $seccion)
                <tr class="n{{ $seccion['nivel'] }}">
                    <td class="numero">{{ $seccion['numero'] }}</td>
                    <td style="padding-left: {{ ($seccion['nivel'] - 1) * 12 + 4 }}pt;">{{ $seccion['titulo'] }}</td>
                    <td class="cuenta">
                        {{ $seccion['archivos'] ? $seccion['archivos'].' documento(s)' : '—' }}
                        @if ($seccion['fuera'])
                            <span class="fuera">· {{ $seccion['fuera'] }} sin incluir</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        @if ($fuera)
            <div class="aviso">
                <b>No se pudieron incluir</b> —el motor de unión actual no abre su formato—: {{ implode(' · ', $fuera) }}.
                Se entregan por separado.
            </div>
        @endif
    </div>
</body>
</html>
