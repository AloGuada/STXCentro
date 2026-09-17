{{-- La hoja que abre cada sección del dosier: su número y su título, grandes. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $seccion['numero'] }} · {{ $seccion['titulo'] }}</title>
    <style>
        @page { margin: 50pt; }
        body { font-family: Helvetica, Arial, sans-serif; color: #111; }
        .separador { text-align: center; padding-top: 210pt; }
        .numero { font-size: 44pt; font-weight: bold; color: #1f3864; }
        .titulo { font-size: 20pt; font-weight: bold; margin: 14pt 40pt 0; line-height: 1.25; }
        .obra { margin-top: 50pt; font-size: 10pt; color: #666; }
    </style>
</head>
<body>
    <div class="separador">
        <div class="numero">{{ $seccion['numero'] }}</div>
        <div class="titulo">{{ $seccion['titulo'] }}</div>
        <div class="obra">{{ $obra }} · Dosier de calidad</div>
    </div>
</body>
</html>
