<?php

namespace App\Http\Controllers\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sti\TicketPublicoStoreRequest;
use App\Models\Departamento;
use App\Models\Sti\Status;
use App\Models\Sti\Ticket;
use App\Models\Sti\TicketHistorial;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketPublicoController extends Controller
{
    public function index(): Response
    {
        $tickets = Ticket::query()
            ->with(['departamento', 'historial.status'])
            ->whereHas('historial', function ($q) {
                $q->whereIn('id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('sti_ticket_historial')
                        ->groupBy('ticket_id');
                })->whereHas('status', fn ($s) => $s->where('orden', '<=', 7));
            })
            ->latest()
            ->paginate(20);

        return Inertia::render('sti/tickets-pendientes', [
            'tickets' => $tickets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sti/ticket-nuevo', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function store(TicketPublicoStoreRequest $request): RedirectResponse
    {
        $ticket = Ticket::create([
            'nombre_solicitante' => $request->nombre_solicitante,
            'comentario' => $request->comentario,
            'departamento_id' => $request->departamento_id,
            'tecnico_id' => null,
            'equipo_id' => null,
        ]);

        // Asignar automáticamente el status "Pendiente"
        $pendiente = Status::where('descripcion', 'Pendiente')->first();
        if ($pendiente) {
            TicketHistorial::create([
                'ticket_id' => $ticket->id,
                'status_id' => $pendiente->id,
            ]);
        }

        return to_route('sti.ticket.create')->with('success', 'Ticket enviado correctamente. Un técnico se pondrá en contacto contigo.');
    }
}
