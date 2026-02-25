<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Models\Sti\Tecnico;
use App\Models\Sti\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $driver = DB::connection()->getDriverName();

        $periodExpr = match ($driver) {
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $roundAvg = $driver === 'pgsql'
            ? 'ROUND(AVG(calificacion)::numeric, 1)'
            : 'ROUND(AVG(calificacion), 1)';

        // KPIs
        $total = Ticket::count();
        $sinAsignar = Ticket::whereNull('tecnico_id')->whereNull('firma_completado')->count();
        $enProceso = Ticket::whereNotNull('tecnico_id')->whereNull('firma_completado')->count();
        $completados = Ticket::whereNotNull('firma_completado')->count();
        $tasaResolucion = $total > 0 ? round($completados / $total * 100, 1) : 0;
        $promedioSatisfaccion = round((float) Ticket::whereNotNull('calificacion')->avg('calificacion'), 2);

        // Tickets por técnico (activos vs completados)
        $ticketsPorTecnico = Tecnico::query()
            ->where('activo', true)
            ->withCount('tickets as total')
            ->withCount(['tickets as completados' => fn ($q) => $q->whereNotNull('firma_completado')])
            ->withCount(['tickets as activos' => fn ($q) => $q->whereNull('firma_completado')])
            ->orderByDesc('total')
            ->get()
            ->filter(fn ($t) => $t->total > 0)
            ->map(fn ($t) => [
                'tecnico' => $t->descripcion,
                'total' => $t->total,
                'completados' => $t->completados,
                'activos' => $t->activos,
            ]);

        // Tendencia mensual (últimos 12 meses)
        $tendenciaMensual = Ticket::query()
            ->selectRaw("{$periodExpr} as periodo, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get()
            ->map(fn ($r) => [
                'periodo' => $r->periodo,
                'total' => $r->total,
            ]);

        // Tickets por departamento
        $ticketsPorDepartamento = Ticket::query()
            ->selectRaw('departamento_id, COUNT(*) as total')
            ->with('departamento:id,descripcion')
            ->groupBy('departamento_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'departamento' => $r->departamento?->descripcion ?? 'Sin departamento',
                'total' => $r->total,
            ]);

        // Distribución de calificaciones (1–5 estrellas)
        $distribucionCalificaciones = Ticket::query()
            ->whereNotNull('calificacion')
            ->selectRaw('calificacion, COUNT(*) as total')
            ->groupBy('calificacion')
            ->orderBy('calificacion')
            ->get()
            ->map(fn ($r) => [
                'estrellas' => $r->calificacion,
                'label' => str_repeat('★', $r->calificacion).str_repeat('☆', 5 - $r->calificacion),
                'total' => $r->total,
            ]);

        // Satisfacción promedio por departamento
        $satisfaccionPorDepartamento = Ticket::query()
            ->whereNotNull('calificacion')
            ->whereNotNull('departamento_id')
            ->with('departamento:id,descripcion')
            ->selectRaw("departamento_id, {$roundAvg} as promedio, COUNT(*) as total")
            ->groupBy('departamento_id')
            ->orderByDesc('promedio')
            ->get()
            ->map(fn ($r) => [
                'departamento' => $r->departamento?->descripcion ?? 'Sin departamento',
                'promedio' => (float) $r->promedio,
                'total' => $r->total,
            ]);

        // Calificación promedio por técnico
        $calificacionesPorTecnico = Ticket::query()
            ->whereNotNull('calificacion')
            ->whereNotNull('tecnico_id')
            ->with('tecnico:id,descripcion')
            ->selectRaw("tecnico_id, {$roundAvg} as promedio, COUNT(*) as total")
            ->groupBy('tecnico_id')
            ->get()
            ->map(fn ($r) => [
                'tecnico' => $r->tecnico?->descripcion ?? 'Desconocido',
                'promedio' => (float) $r->promedio,
                'total' => $r->total,
            ]);

        return Inertia::render('admin/sti/dashboard/index', [
            'kpis' => [
                'total' => $total,
                'sin_asignar' => $sinAsignar,
                'en_proceso' => $enProceso,
                'completados' => $completados,
                'tasa_resolucion' => $tasaResolucion,
                'promedio_satisfaccion' => $promedioSatisfaccion,
            ],
            'tickets_por_tecnico' => $ticketsPorTecnico,
            'tendencia_mensual' => $tendenciaMensual,
            'tickets_por_departamento' => $ticketsPorDepartamento,
            'distribucion_calificaciones' => $distribucionCalificaciones,
            'satisfaccion_por_departamento' => $satisfaccionPorDepartamento,
            'calificaciones_por_tecnico' => $calificacionesPorTecnico,
            'tiempos' => $this->calcularTiempos(),
        ]);
    }

    /**
     * Calcula tiempos de atención reales descontando los estados que detienen el tiempo.
     *
     * @return array{
     *   promedio_total: float,
     *   promedio_activo: float,
     *   promedio_detenido: float,
     *   total_analizados: int,
     *   por_tecnico: array<int, array{tecnico: string, promedio_total: float, promedio_activo: float, promedio_detenido: float}>,
     *   estados_detencion: array<int, array{estado: string, promedio_h: float, ocurrencias: int}>
     * }
     */
    private function calcularTiempos(): array
    {
        $tickets = Ticket::query()
            ->whereNotNull('firma_completado')
            ->whereHas('historial')
            ->with([
                'historial' => fn ($q) => $q->with('status:id,descripcion,detiene_tiempo')->orderBy('created_at'),
                'tecnico:id,descripcion',
            ])
            ->get();

        $porTicket = [];
        $estadosDetencion = [];

        foreach ($tickets as $ticket) {
            $entries = $ticket->historial->sortBy('created_at')->values();
            if ($entries->isEmpty()) {
                continue;
            }

            $totalMin = 0;
            $detenidoMin = 0;

            for ($i = 0; $i < $entries->count(); $i++) {
                $current = $entries[$i];
                $next = $entries[$i + 1] ?? null;

                $inicio = Carbon::parse($current->created_at);
                $fin = $next
                    ? Carbon::parse($next->created_at)
                    : Carbon::parse($ticket->updated_at);

                $duracion = max(0, $inicio->diffInMinutes($fin));
                $totalMin += $duracion;

                if ($current->status?->detiene_tiempo) {
                    $detenidoMin += $duracion;
                    $nombre = $current->status->descripcion;
                    $estadosDetencion[$nombre] ??= ['total_min' => 0, 'ocurrencias' => 0];
                    $estadosDetencion[$nombre]['total_min'] += $duracion;
                    $estadosDetencion[$nombre]['ocurrencias']++;
                }
            }

            $porTicket[] = [
                'tecnico' => $ticket->tecnico?->descripcion,
                'total_h' => round($totalMin / 60, 2),
                'activo_h' => round(($totalMin - $detenidoMin) / 60, 2),
                'detenido_h' => round($detenidoMin / 60, 2),
            ];
        }

        $coleccion = collect($porTicket);

        $porTecnico = $coleccion
            ->filter(fn ($t) => $t['tecnico'] !== null)
            ->groupBy('tecnico')
            ->map(fn ($grupo, $nombre) => [
                'tecnico' => $nombre,
                'promedio_total' => round($grupo->avg('total_h'), 1),
                'promedio_activo' => round($grupo->avg('activo_h'), 1),
                'promedio_detenido' => round($grupo->avg('detenido_h'), 1),
            ])
            ->values();

        $estadosOrdenados = collect($estadosDetencion)
            ->map(fn ($data, $estado) => [
                'estado' => $estado,
                'promedio_h' => round($data['total_min'] / $data['ocurrencias'] / 60, 1),
                'ocurrencias' => $data['ocurrencias'],
            ])
            ->sortByDesc('promedio_h')
            ->values();

        return [
            'promedio_total' => round((float) $coleccion->avg('total_h'), 1),
            'promedio_activo' => round((float) $coleccion->avg('activo_h'), 1),
            'promedio_detenido' => round((float) $coleccion->avg('detenido_h'), 1),
            'total_analizados' => $coleccion->count(),
            'por_tecnico' => $porTecnico,
            'estados_detencion' => $estadosOrdenados,
        ];
    }
}
