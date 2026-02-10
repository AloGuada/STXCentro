<?php

use App\Models\Sti\Status;
use App\Models\Sti\Ticket;
use App\Models\Sti\TicketHistorial;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('ticket update calificacion', function () {
    test('update without calificacion passes validation when status is not completado', function () {
        $ticket = Ticket::factory()->create();
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'status_id' => $statusPendiente->id,
                'calificacion' => null,
                'firma_completado' => '',
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));
        $response->assertSessionHasNoErrors();
    });

    test('update with calificacion zero does not cause validation error', function () {
        $ticket = Ticket::factory()->create();
        $statusPendiente = Status::factory()->create(['descripcion' => 'En Proceso', 'orden' => 3]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'status_id' => $statusPendiente->id,
                'calificacion' => null,
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));
        $response->assertSessionHasNoErrors();
    });

    test('update with valid calificacion on completado status works', function () {
        $ticket = Ticket::factory()->create();
        $statusCompletado = Status::factory()->create(['descripcion' => 'Completado', 'orden' => 8]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusCompletado->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'status_id' => $statusCompletado->id,
                'calificacion' => 4,
                'firma_completado' => 'data:image/png;base64,test',
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));
        $response->assertSessionHasNoErrors();

        $ticket->refresh();
        expect($ticket->calificacion)->toBe(4);
    });

    test('calificacion is not saved when null is sent', function () {
        $ticket = Ticket::factory()->create(['calificacion' => null]);
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'status_id' => $statusPendiente->id,
                'calificacion' => null,
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));

        $ticket->refresh();
        expect($ticket->calificacion)->toBeNull();
    });
});
