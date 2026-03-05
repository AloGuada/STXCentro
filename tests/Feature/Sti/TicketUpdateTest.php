<?php

use App\Models\Sti\Status;
use App\Models\Sti\Tecnico;
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
        $tecnico = Tecnico::factory()->create();
        $ticket = Ticket::factory()->create();
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);
        $statusCompletado = Status::factory()->create(['descripcion' => 'Completado', 'orden' => 8]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'tecnico_id' => $tecnico->id,
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

        $ticket->refresh();
        expect($ticket->calificacion)->toBeNull();
    });

    test('tecnico is required when changing status', function () {
        $ticket = Ticket::factory()->create();
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);
        $statusTrabajando = Status::factory()->create(['descripcion' => 'Trabajando', 'orden' => 2]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'status_id' => $statusTrabajando->id,
            ]);

        $response->assertSessionHasErrors(['tecnico_id']);
    });

    test('tecnico is not required when status stays the same', function () {
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
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));
        $response->assertSessionHasNoErrors();
    });

    test('calificacion is required when status is completado', function () {
        $tecnico = Tecnico::factory()->create();
        $ticket = Ticket::factory()->create();
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);
        $statusCompletado = Status::factory()->create(['descripcion' => 'Completado', 'orden' => 8]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'tecnico_id' => $tecnico->id,
                'status_id' => $statusCompletado->id,
                'calificacion' => null,
                'firma_completado' => 'data:image/png;base64,test',
            ]);

        $response->assertSessionHasErrors(['calificacion']);
    });

    test('firma is required when status is completado and ticket has no firma', function () {
        $tecnico = Tecnico::factory()->create();
        $ticket = Ticket::factory()->create(['firma_completado' => null]);
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);
        $statusCompletado = Status::factory()->create(['descripcion' => 'Completado', 'orden' => 8]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'tecnico_id' => $tecnico->id,
                'status_id' => $statusCompletado->id,
                'calificacion' => 5,
                'firma_completado' => '',
            ]);

        $response->assertSessionHasErrors(['firma_completado']);
    });

    test('firma is not required when ticket already has firma', function () {
        $tecnico = Tecnico::factory()->create();
        $ticket = Ticket::factory()->create(['firma_completado' => 'data:image/png;base64,existing']);
        $statusPendiente = Status::factory()->create(['descripcion' => 'Pendiente', 'orden' => 1]);
        $statusCompletado = Status::factory()->create(['descripcion' => 'Completado', 'orden' => 8]);

        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statusPendiente->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.tickets.update', $ticket), [
                'nombre_solicitante' => $ticket->nombre_solicitante,
                'comentario' => $ticket->comentario,
                'departamento_id' => $ticket->departamento_id,
                'tecnico_id' => $tecnico->id,
                'status_id' => $statusCompletado->id,
                'calificacion' => 4,
            ]);

        $response->assertRedirect(route('admin.sti.tickets.index'));
        $response->assertSessionHasNoErrors();
    });
});
