<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gantt Anual {{ $year }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; padding: 15px 20px; }
        h1 { font-size: 16px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 3px; text-align: center; }
        th { background-color: #f3f4f6; font-size: 8px; font-weight: bold; }
        td.equipo-cell { text-align: left; min-width: 120px; font-size: 8px; }
        td.info-cell { text-align: left; min-width: 90px; font-size: 8px; }
        .dot-realizado { display: inline-block; width: 14px; height: 14px; border-radius: 3px; background-color: #22c55e; color: #fff; font-size: 7px; line-height: 14px; text-align: center; margin: 1px; }
        .dot-pendiente { display: inline-block; width: 14px; height: 14px; border-radius: 3px; background-color: #f97316; color: #fff; font-size: 7px; line-height: 14px; text-align: center; margin: 1px; }
        .leyenda { margin-top: 15px; font-size: 9px; }
        .leyenda span { display: inline-block; width: 12px; height: 12px; border-radius: 2px; vertical-align: middle; margin-right: 3px; }
    </style>
</head>
<body>
    <h1>Programa de Mantenimiento Anual - {{ $year }}</h1>

    @if ($total > 0)
        <p style="font-size: 11px; margin-bottom: 8px; color: #333;">
            <strong>Mantenimientos cumplidos: {{ $pctAvance }}%</strong> &nbsp; {{ $realizados }}/{{ $total }}. &nbsp;&nbsp;
            <strong>Efectividad del: {{ $efectividad }}%</strong>
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th style="text-align: left;">Equipo</th>
                <th style="text-align: left;">Asignado a</th>
                <th style="text-align: left;">Departamento</th>
                @foreach (['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $mes)
                    <th>{{ $mes }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($datos as $fila)
                <tr>
                    <td class="equipo-cell">{{ $fila['equipo'] }}</td>
                    <td class="info-cell">{{ $fila['asignado_a'] }}</td>
                    <td class="info-cell">{{ $fila['departamento'] }}</td>
                    @foreach ($fila['meses'] as $mants)
                        <td>
                            @foreach ($mants as $m)
                                <span class="dot-{{ $m['status'] }}">{{ $m['dia'] }}</span>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="padding: 20px; text-align: center; color: #888;">No hay mantenimientos programados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="leyenda">
        <span style="background-color: #f97316;"></span> Pendiente &nbsp;&nbsp;
        <span style="background-color: #22c55e;"></span> Realizado
    </div>
</body>
</html>
