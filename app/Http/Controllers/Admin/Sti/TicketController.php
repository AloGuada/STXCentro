<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\CostoStoreRequest;
use App\Http\Requests\Admin\Sti\TicketStoreRequest;
use App\Http\Requests\Admin\Sti\TicketUpdateRequest;
use App\Models\Departamento;
use App\Models\Sti\CostoMantenimiento;
use App\Models\Sti\Equipo;
use App\Models\Sti\Status;
use App\Models\Sti\Tecnico;
use App\Models\Sti\Ticket;
use App\Models\Sti\TicketComentario;
use App\Models\Sti\TicketHistorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $driver = DB::connection()->getDriverName();

        $periodExpr = match ($driver) {
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $tickets = Ticket::query()
            ->with(['tecnico', 'equipo', 'departamento', 'historial.status'])
            ->when($request->search, fn ($q, $s) => $q->where('nombre_solicitante', 'like', "%{$s}%")
                ->orWhere('comentario', 'like', "%{$s}%"))
            ->when($request->tecnico_id, fn ($q, $id) => $q->where('tecnico_id', $id))
            ->when($request->departamento_id, fn ($q, $id) => $q->where('departamento_id', $id))
            ->when($request->calificacion, fn ($q, $c) => $q->where('calificacion', $c))
            ->when($request->periodo, fn ($q, $p) => $q->whereRaw("{$periodExpr} = ?", [$p]))
            ->when($request->estado, function ($q, $estado) {
                return match ($estado) {
                    'sin_asignar' => $q->whereNull('tecnico_id')->whereNull('firma_completado'),
                    'en_proceso' => $q->whereNotNull('tecnico_id')->whereNull('firma_completado'),
                    'completados' => $q->whereNotNull('firma_completado'),
                    default => $q,
                };
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/tickets/index', [
            'tickets' => $tickets,
            'filters' => $request->only('search', 'tecnico_id', 'departamento_id', 'calificacion', 'estado', 'periodo'),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/tickets/create', [
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'statuses' => Status::orderBy('orden')->get(['id', 'descripcion']),
        ]);
    }

    public function store(TicketStoreRequest $request): RedirectResponse
    {
        $ticket = Ticket::create([
            'nombre_solicitante' => $request->nombre_solicitante,
            'comentario' => $request->comentario,
            'tecnico_id' => $request->tecnico_id ?: null,
            'equipo_id' => $request->equipo_id ?: null,
            'departamento_id' => $request->departamento_id,
        ]);

        // Si no se proporciona status, asignar "Pendiente" por defecto
        $statusId = $request->status_id;
        if (! $statusId) {
            $pendiente = Status::where('descripcion', 'Pendiente')->first();
            $statusId = $pendiente?->id;
        }

        if ($statusId) {
            TicketHistorial::create([
                'ticket_id' => $ticket->id,
                'status_id' => $statusId,
            ]);
        }

        return to_route('admin.sti.tickets.index');
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load(['tecnico', 'equipo', 'departamento', 'historial.status', 'costos']);

        return Inertia::render('admin/sti/tickets/show', [
            'ticket' => $ticket,
        ]);
    }

    public function edit(Ticket $ticket): Response
    {
        $ticket->load(['tecnico', 'equipo', 'departamento', 'historial.status', 'costos', 'comentarios']);

        return Inertia::render('admin/sti/tickets/edit', [
            'ticket' => $ticket,
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'statuses' => Status::orderBy('orden')->get(['id', 'descripcion', 'orden']),
        ]);
    }

    public function update(TicketUpdateRequest $request, Ticket $ticket): RedirectResponse
    {
        $updateData = [
            'nombre_solicitante' => $request->nombre_solicitante,
            'comentario' => $request->comentario,
            'tecnico_id' => $request->tecnico_id ?: null,
            'equipo_id' => $request->equipo_id ?: null,
            'departamento_id' => $request->departamento_id,
        ];

        // Determinar si el nuevo estado es de tipo "completado"
        $newStatus = $request->status_id ? Status::find($request->status_id) : null;
        $isCompletado = $newStatus && str_starts_with(mb_strtolower($newStatus->descripcion), 'completado');

        if ($isCompletado) {
            // Agregar firma y calificación solo si se proporcionan valores reales
            if ($request->filled('firma_completado')) {
                $updateData['firma_completado'] = $request->firma_completado;
            }
            if ($request->filled('calificacion')) {
                $updateData['calificacion'] = $request->calificacion;
            }
        } else {
            // Limpiar firma y calificación al regresar a un estado no completado
            $updateData['firma_completado'] = null;
            $updateData['calificacion'] = null;
        }

        $ticket->update($updateData);

        if ($request->status_id) {
            $lastStatus = $ticket->historial()->latest('id')->first();
            if (! $lastStatus || $lastStatus->status_id != $request->status_id) {
                TicketHistorial::create([
                    'ticket_id' => $ticket->id,
                    'status_id' => $request->status_id,
                ]);
            }
        }

        return to_route('admin.sti.tickets.index');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $ticket->costos()->delete();
        $ticket->historial()->delete();
        $ticket->delete();

        return to_route('admin.sti.tickets.index');
    }

    public function storeCosto(CostoStoreRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->costos()->create([
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
        ]);

        return back()->with('success', 'Costo agregado correctamente.');
    }

    public function destroyCosto(Ticket $ticket, CostoMantenimiento $costo): RedirectResponse
    {
        if ($costo->costeable_id !== $ticket->id || $costo->costeable_type !== Ticket::class) {
            abort(404);
        }

        $costo->delete();

        return back()->with('success', 'Costo eliminado correctamente.');
    }

    public function storeComentario(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'comentario' => ['required', 'string', 'max:2000'],
        ]);

        TicketComentario::create([
            'ticket_id' => $ticket->id,
            'comentario' => $request->comentario,
            'autor' => auth()->user()->name ?? 'Tecnico',
            'tipo' => 'tecnico',
        ]);

        return back()->with('success', 'Comentario agregado correctamente.');
    }
}
