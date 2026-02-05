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
use App\Models\Sti\TicketHistorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->with(['tecnico', 'equipo', 'departamento', 'historial.status'])
            ->when($request->search, fn ($q, $s) => $q->where('nombre_solicitante', 'like', "%{$s}%")
                ->orWhere('comentario', 'like', "%{$s}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/tickets/index', [
            'tickets' => $tickets,
            'filters' => $request->only('search'),
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
        $ticket->load(['tecnico', 'equipo', 'departamento', 'historial.status', 'costos']);

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

        // Agregar firma y calificación si se proporcionan
        if ($request->has('firma_completado')) {
            $updateData['firma_completado'] = $request->firma_completado;
        }
        if ($request->has('calificacion')) {
            $updateData['calificacion'] = $request->calificacion;
        }

        $ticket->update($updateData);

        if ($request->status_id) {
            $lastStatus = $ticket->historial()->latest()->first();
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
}
