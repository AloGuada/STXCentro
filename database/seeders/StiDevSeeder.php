<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Sti\Status;
use App\Models\Sti\Tecnico;
use App\Models\Sti\Ticket;
use App\Models\Sti\TicketHistorial;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StiDevSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar datos previos de STI
        DB::table('sti_ticket_historial')->delete();
        DB::table('sti_tickets')->delete();

        // Departamentos fijos
        $departamentos = collect([
            'Producción', 'Administración', 'Recursos Humanos', 'Calidad', 'Almacén', 'Logística',
        ])->map(fn ($nombre) => Departamento::firstOrCreate(['descripcion' => $nombre], ['manager' => fake()->name()]));

        // Técnicos fijos
        $tecnicos = collect(['Carlos Méndez', 'Ana Ríos', 'Jorge Salinas'])->map(
            fn ($nombre) => Tecnico::firstOrCreate(['descripcion' => $nombre], ['activo' => true])
        );

        // Cargar estados necesarios
        $statuses = Status::all()->keyBy('descripcion');
        $sPendiente = $statuses['Pendiente'];
        $sTrabajando = $statuses['Trabajando'];
        $sCompletado = $statuses['Completado'];
        $sEsperaUsuario = $statuses['En espera del usuario'];
        $sEsperaProveedor = $statuses['En espera del proveedor'];
        $sEsperaRequisicion = $statuses['En requisición de compras'];

        // Calificaciones con sesgo positivo
        $calificaciones = [5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 1];

        // Generar tickets distribuidos en los últimos 12 meses con historial realista
        for ($m = 11; $m >= 0; $m--) {
            $inicio = Carbon::now()->subMonths($m)->startOfMonth();
            $cantidad = random_int(6, 18);

            for ($i = 0; $i < $cantidad; $i++) {
                $tecnico = fake()->randomElement([...$tecnicos, null]);
                $completado = $tecnico && fake()->boolean(65);
                $createdAt = $inicio->copy()->addDays(random_int(0, 27))->addHours(random_int(7, 17));

                $ticket = Ticket::factory()->create([
                    'departamento_id' => $departamentos->random()->id,
                    'tecnico_id' => $tecnico?->id,
                    'firma_completado' => $completado ? fake()->sha1() : null,
                    'calificacion' => $completado ? fake()->randomElement($calificaciones) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $this->crearHistorial($ticket, $createdAt, $tecnico, $completado, [
                    'pendiente' => $sPendiente,
                    'trabajando' => $sTrabajando,
                    'completado' => $sCompletado,
                    'espera_usuario' => $sEsperaUsuario,
                    'espera_proveedor' => $sEsperaProveedor,
                    'espera_requisicion' => $sEsperaRequisicion,
                ]);
            }
        }
    }

    /**
     * @param  array<string, Status>  $statuses
     */
    private function crearHistorial(Ticket $ticket, Carbon $createdAt, ?Tecnico $tecnico, bool $completado, array $statuses): void
    {
        $cursor = $createdAt->copy();

        // Siempre entra como Pendiente
        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statuses['pendiente']->id,
            'created_at' => $cursor,
            'updated_at' => $cursor,
        ]);

        if (! $tecnico) {
            // Sin asignar: se queda en Pendiente
            return;
        }

        // Técnico asigna: pasa a Trabajando después de 1–4 h
        $cursor = $cursor->copy()->addHours(random_int(1, 4))->addMinutes(random_int(0, 59));
        TicketHistorial::create([
            'ticket_id' => $ticket->id,
            'status_id' => $statuses['trabajando']->id,
            'created_at' => $cursor,
            'updated_at' => $cursor,
        ]);

        // 40% de probabilidad de tener un estado de detención
        if (fake()->boolean(40)) {
            $statusDetencion = fake()->randomElement([
                $statuses['espera_usuario'],
                $statuses['espera_proveedor'],
                $statuses['espera_requisicion'],
            ]);

            // Trabaja un rato antes de quedar detenido (2–8 h)
            $cursor = $cursor->copy()->addHours(random_int(2, 8))->addMinutes(random_int(0, 59));
            TicketHistorial::create([
                'ticket_id' => $ticket->id,
                'status_id' => $statusDetencion->id,
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);

            // Espera detenida: 4–48 h
            $cursor = $cursor->copy()->addHours(random_int(4, 48));
            TicketHistorial::create([
                'ticket_id' => $ticket->id,
                'status_id' => $statuses['trabajando']->id,
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);
        }

        if ($completado) {
            // Trabaja un rato más y completa (1–6 h)
            $cursor = $cursor->copy()->addHours(random_int(1, 6))->addMinutes(random_int(0, 59));
            TicketHistorial::create([
                'ticket_id' => $ticket->id,
                'status_id' => $statuses['completado']->id,
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);
        }
    }
}
