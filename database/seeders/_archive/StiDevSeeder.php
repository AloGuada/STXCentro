<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Check;
use App\Models\Sti\Equipo;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\Sti\ItemTipo;
use App\Models\Sti\Plan;
use App\Models\Sti\Status;
use App\Models\Sti\Tecnico;
use App\Models\Sti\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StiDevSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::table('sti_check_ejecuciones')->delete();
        DB::table('sti_mantenimientos')->delete();
        DB::table('sti_checks')->delete();
        DB::table('sti_planes')->delete();
        DB::table('sti_asignacion_activos')->delete();
        DB::table('sti_grupos')->delete();
        DB::table('sti_items')->delete();
        DB::table('sti_items_tipos')->delete();
        DB::table('sti_ticket_historial')->delete();
        DB::table('sti_tickets')->delete();
        DB::table('sti_equipos')->delete();
        DB::statement('PRAGMA foreign_keys = ON');

        $this->seedEquiposItemsPlanes();
        $this->seedMantenimientos();
        $this->seedAsignaciones();
        $this->seedTickets();
    }

    private function seedEquiposItemsPlanes(): void
    {
        // Departamentos fijos
        collect(['Producción', 'Administración', 'Recursos Humanos', 'Calidad', 'Almacén', 'Logística'])
            ->each(fn ($n) => Departamento::firstOrCreate(['descripcion' => $n], ['manager' => fake()->name()]));

        // Técnicos fijos
        collect(['Carlos Méndez', 'Ana Ríos', 'Jorge Salinas'])
            ->each(fn ($n) => Tecnico::firstOrCreate(['descripcion' => $n], ['activo' => true]));

        // Equipos
        $equiposData = [
            ['descripcion' => 'Laptop Dell Latitude 5540', 'serie' => 'DLL001234', 'marca' => 'Dell', 'factor_criticidad' => 2],
            ['descripcion' => 'Laptop HP ProBook 450 G10', 'serie' => 'HPP005678', 'marca' => 'HP', 'factor_criticidad' => 2],
            ['descripcion' => 'Desktop Lenovo ThinkCentre M70q', 'serie' => 'LNV009012', 'marca' => 'Lenovo', 'factor_criticidad' => 1],
            ['descripcion' => 'Servidor Dell PowerEdge R750', 'serie' => 'DLS003456', 'marca' => 'Dell', 'factor_criticidad' => 4],
            ['descripcion' => 'Impresora HP LaserJet Pro M404', 'serie' => 'HPI007890', 'marca' => 'HP', 'factor_criticidad' => 1],
            ['descripcion' => 'Switch Cisco Catalyst 1000', 'serie' => 'CSC001122', 'marca' => 'Dell', 'factor_criticidad' => 3],
            ['descripcion' => 'UPS APC Smart-UPS 1500', 'serie' => 'APC003344', 'marca' => 'Acer', 'factor_criticidad' => 3],
            ['descripcion' => 'Desktop Dell OptiPlex 7010', 'serie' => 'DLD005566', 'marca' => 'Dell', 'factor_criticidad' => 2],
            ['descripcion' => 'Laptop Lenovo ThinkPad T14', 'serie' => 'LNL007788', 'marca' => 'Lenovo', 'factor_criticidad' => 2],
            ['descripcion' => 'Access Point Ubiquiti U6-Pro', 'serie' => 'UBQ009900', 'marca' => 'Asus', 'factor_criticidad' => 3],
            ['descripcion' => 'Proyector Epson PowerLite X49', 'serie' => 'EPS001111', 'marca' => 'Acer', 'factor_criticidad' => 1],
            ['descripcion' => 'Servidor HP ProLiant DL380', 'serie' => 'HPS002222', 'marca' => 'HP', 'factor_criticidad' => 4],
        ];
        DB::table('sti_equipos')->insert(array_map(fn ($d) => $d + ['created_at' => now(), 'updated_at' => now()], $equiposData));

        // Tipos de items
        $tiposNombres = ['RAM', 'HDD', 'SSD', 'Procesador', 'Tarjeta Madre', 'Fuente de Poder', 'Monitor', 'Teclado', 'Mouse'];
        DB::table('sti_items_tipos')->insert(array_map(fn ($n) => ['descripcion' => $n, 'created_at' => now(), 'updated_at' => now()], $tiposNombres));

        $equipos = Equipo::all();
        $tipos = ItemTipo::pluck('id')->toArray();
        $accesorios = ['Cable HDMI', 'Adaptador USB-C', 'Memoria USB 32GB', 'Mouse inalámbrico', 'Teclado externo', 'Docking Station', 'Cargador original'];

        foreach ($equipos as $equipo) {
            $principal = Item::create([
                'descripcion' => $equipo->descripcion.' - Principal',
                'tipo_id' => $tipos[array_rand($tipos)],
                'costo' => rand(5000, 30000),
                'no_serie' => $equipo->serie.'-P',
                'estado' => 'instalado',
                'principal' => true,
                'accesorio' => false,
            ]);
            Grupo::create(['equipo_id' => $equipo->id, 'item_id' => $principal->id]);

            for ($a = 0; $a < 2; $a++) {
                $item = Item::create([
                    'descripcion' => $accesorios[array_rand($accesorios)],
                    'tipo_id' => $tipos[array_rand($tipos)],
                    'costo' => rand(100, 2000),
                    'no_serie' => strtoupper(substr(md5(uniqid()), 0, 10)),
                    'estado' => 'instalado',
                    'principal' => false,
                    'accesorio' => true,
                ]);
                Grupo::create(['equipo_id' => $equipo->id, 'item_id' => $item->id]);
            }
        }

        // Planes con checks
        $planesData = [
            ['desc' => 'Mantenimiento preventivo mensual - Laptops', 'per' => 30, 'fecha' => '2026-01-15', 'checks' => ['Limpieza de ventilador', 'Verificar temperatura CPU', 'Actualizar sistema operativo', 'Revisar estado de batería', 'Limpiar pantalla y teclado']],
            ['desc' => 'Mantenimiento trimestral - Servidores', 'per' => 90, 'fecha' => '2026-01-01', 'checks' => ['Verificar logs del sistema', 'Revisar espacio en disco', 'Comprobar backups', 'Verificar estado de RAID', 'Limpiar filtros de aire']],
            ['desc' => 'Mantenimiento semestral - Red', 'per' => 180, 'fecha' => '2026-01-10', 'checks' => ['Verificar firmware de switches', 'Revisar configuración VLAN', 'Probar redundancia', 'Medir velocidad de enlaces']],
            ['desc' => 'Revisión bimestral - UPS', 'per' => 60, 'fecha' => '2026-01-05', 'checks' => ['Prueba de autonomía', 'Verificar voltaje de baterías', 'Limpiar contactos', 'Revisar ventilación']],
        ];

        foreach ($planesData as $pd) {
            $plan = Plan::create(['descripcion' => $pd['desc'], 'periodicidad' => $pd['per'], 'fecha_inicial' => $pd['fecha'], 'activo' => true]);
            foreach ($pd['checks'] as $i => $c) {
                Check::create(['plan_id' => $plan->id, 'descripcion' => $c, 'orden' => $i + 1]);
            }
        }
    }

    private function seedMantenimientos(): void
    {
        $equipos = Equipo::all();
        $tecnicoIds = Tecnico::pluck('id')->toArray();
        $planes = Plan::with('checks')->get();
        $now = now()->toDateTimeString();

        // Mapa: plan → equipos correspondientes
        $planEquipoMap = [
            0 => $equipos->filter(fn ($e) => str_contains($e->descripcion, 'Laptop'))->pluck('id'),
            1 => $equipos->filter(fn ($e) => str_contains($e->descripcion, 'Servidor'))->pluck('id'),
            2 => $equipos->filter(fn ($e) => str_contains($e->descripcion, 'Switch') || str_contains($e->descripcion, 'Access Point'))->pluck('id'),
            3 => $equipos->filter(fn ($e) => str_contains($e->descripcion, 'UPS'))->pluck('id'),
        ];

        foreach ($planes as $idx => $plan) {
            $equipoIds = $planEquipoMap[$idx] ?? collect();
            $checkIds = $plan->checks->pluck('id')->toArray();

            foreach ($equipoIds as $equipoId) {
                $fecha = Carbon::parse($plan->fecha_inicial);
                $finAnio = Carbon::create(2026, 12, 31);

                while ($fecha->lte($finAnio)) {
                    $esPasado = $fecha->lt(now());

                    $mantId = DB::table('sti_mantenimientos')->insertGetId([
                        'equipo_id' => $equipoId,
                        'plan_id' => $plan->id,
                        'fecha_programada' => $fecha->toDateString(),
                        'descripcion' => $plan->descripcion,
                        'tecnico_id' => $tecnicoIds[array_rand($tecnicoIds)],
                        'status' => $esPasado ? 'realizado' : 'pendiente',
                        'fecha_realizado' => $esPasado ? $fecha->copy()->addDays(rand(0, 2))->toDateString() : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $ejecuciones = [];
                    foreach ($checkIds as $checkId) {
                        $ejecuciones[] = [
                            'mantenimiento_id' => $mantId,
                            'check_id' => $checkId,
                            'resultado' => $esPasado,
                            'observaciones' => null,
                            'tecnico_id' => $esPasado ? $tecnicoIds[array_rand($tecnicoIds)] : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('sti_check_ejecuciones')->insert($ejecuciones);

                    $fecha->addDays($plan->periodicidad);
                }
            }
        }

        // Correctivos
        $correctivos = [
            'Reemplazo de disco duro dañado', 'Falla en fuente de poder',
            'Pantalla no enciende - diagnóstico', 'Ruido excesivo en ventilador',
            'Error de memoria RAM', 'Puerto USB no funciona',
            'Sobrecalentamiento frecuente', 'Falla en tarjeta de red',
        ];
        $equipoIds = $equipos->pluck('id')->toArray();
        foreach ($correctivos as $desc) {
            $fechaProg = Carbon::now()->subDays(rand(1, 90));
            $realizado = (bool) rand(0, 1);
            DB::table('sti_mantenimientos')->insert([
                'equipo_id' => $equipoIds[array_rand($equipoIds)],
                'plan_id' => null,
                'fecha_programada' => $fechaProg->toDateString(),
                'descripcion' => $desc,
                'tecnico_id' => $tecnicoIds[array_rand($tecnicoIds)],
                'status' => $realizado ? 'realizado' : 'pendiente',
                'fecha_realizado' => $realizado ? $fechaProg->copy()->addDays(rand(1, 5))->toDateString() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedAsignaciones(): void
    {
        $equipos = Equipo::all();
        $departamentoIds = Departamento::pluck('id')->toArray();
        $tecnicoNombres = Tecnico::pluck('descripcion')->toArray();

        $empleados = [
            ['no' => 'EMP001', 'nombre' => 'María García López'],
            ['no' => 'EMP002', 'nombre' => 'Juan Rodríguez Pérez'],
            ['no' => 'EMP003', 'nombre' => 'Ana Martínez Hernández'],
            ['no' => 'EMP004', 'nombre' => 'Roberto Sánchez Cruz'],
            ['no' => 'EMP005', 'nombre' => 'Laura Torres Ramírez'],
            ['no' => 'EMP006', 'nombre' => 'Carlos Flores Díaz'],
            ['no' => 'EMP007', 'nombre' => 'Patricia Morales Ruiz'],
            ['no' => 'EMP008', 'nombre' => 'Miguel Vargas Castillo'],
        ];

        $asignables = $equipos->filter(fn ($e) => str_contains($e->descripcion, 'Laptop') || str_contains($e->descripcion, 'Desktop'));
        $idx = 0;

        foreach ($asignables as $equipo) {
            if (! isset($empleados[$idx])) {
                break;
            }
            AsignacionActivo::create([
                'departamento_id' => $departamentoIds[array_rand($departamentoIds)],
                'equipo_id' => $equipo->id,
                'no_empleado' => $empleados[$idx]['no'],
                'empleado' => $empleados[$idx]['nombre'],
                'no_ti' => 'TI00'.($idx + 1),
                'nombre_ti' => $tecnicoNombres[array_rand($tecnicoNombres)],
                'fecha_inicial' => Carbon::now()->subMonths(rand(1, 12)),
                'estado' => 'activo',
            ]);
            $idx++;
        }

        // 2 devueltas
        $equipoIds = $equipos->pluck('id')->toArray();
        for ($i = 0; $i < 2; $i++) {
            $fechaInicio = Carbon::now()->subMonths(rand(6, 18));
            AsignacionActivo::create([
                'departamento_id' => $departamentoIds[array_rand($departamentoIds)],
                'equipo_id' => $equipoIds[array_rand($equipoIds)],
                'no_empleado' => 'EMP'.rand(100, 999),
                'empleado' => fake()->name(),
                'no_ti' => 'TI'.rand(100, 999),
                'nombre_ti' => $tecnicoNombres[array_rand($tecnicoNombres)],
                'fecha_inicial' => $fechaInicio,
                'fecha_termino' => $fechaInicio->copy()->addMonths(rand(3, 6)),
                'estado' => 'devuelto',
            ]);
        }
    }

    private function seedTickets(): void
    {
        $departamentoIds = Departamento::pluck('id')->toArray();
        $tecnicos = Tecnico::all();
        $statuses = Status::all()->keyBy('descripcion');
        $calificaciones = [5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 1];

        $statusMap = [
            'pendiente' => $statuses['Pendiente'],
            'trabajando' => $statuses['Trabajando'],
            'completado' => $statuses['Completado'],
            'espera_usuario' => $statuses['En espera del usuario'],
            'espera_proveedor' => $statuses['En espera del proveedor'],
            'espera_requisicion' => $statuses['En requisición de compras'],
        ];

        $historialBulk = [];

        for ($m = 11; $m >= 0; $m--) {
            $inicio = Carbon::now()->subMonths($m)->startOfMonth();
            $cantidad = rand(4, 10);

            for ($i = 0; $i < $cantidad; $i++) {
                $tecnico = rand(0, 3) > 0 ? $tecnicos->random() : null;
                $completado = $tecnico && rand(1, 100) <= 65;
                $createdAt = $inicio->copy()->addDays(rand(0, 27))->addHours(rand(7, 17));

                $ticket = Ticket::factory()->create([
                    'departamento_id' => $departamentoIds[array_rand($departamentoIds)],
                    'tecnico_id' => $tecnico?->id,
                    'firma_completado' => $completado ? md5(uniqid()) : null,
                    'calificacion' => $completado ? $calificaciones[array_rand($calificaciones)] : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $this->buildHistorial($historialBulk, $ticket->id, $createdAt, $tecnico, $completado, $statusMap);
            }
        }

        foreach (array_chunk($historialBulk, 100) as $chunk) {
            DB::table('sti_ticket_historial')->insert($chunk);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $bulk
     * @param  array<string, Status>  $statuses
     */
    private function buildHistorial(array &$bulk, int $ticketId, Carbon $createdAt, ?Tecnico $tecnico, bool $completado, array $statuses): void
    {
        $cursor = $createdAt->copy();

        $bulk[] = ['ticket_id' => $ticketId, 'status_id' => $statuses['pendiente']->id, 'created_at' => $cursor->toDateTimeString(), 'updated_at' => $cursor->toDateTimeString()];

        if (! $tecnico) {
            return;
        }

        $cursor = $cursor->copy()->addHours(rand(1, 4))->addMinutes(rand(0, 59));
        $bulk[] = ['ticket_id' => $ticketId, 'status_id' => $statuses['trabajando']->id, 'created_at' => $cursor->toDateTimeString(), 'updated_at' => $cursor->toDateTimeString()];

        if (rand(1, 100) <= 40) {
            $detencion = [$statuses['espera_usuario'], $statuses['espera_proveedor'], $statuses['espera_requisicion']];
            $statusDet = $detencion[array_rand($detencion)];

            $cursor = $cursor->copy()->addHours(rand(2, 8))->addMinutes(rand(0, 59));
            $bulk[] = ['ticket_id' => $ticketId, 'status_id' => $statusDet->id, 'created_at' => $cursor->toDateTimeString(), 'updated_at' => $cursor->toDateTimeString()];

            $cursor = $cursor->copy()->addHours(rand(4, 48));
            $bulk[] = ['ticket_id' => $ticketId, 'status_id' => $statuses['trabajando']->id, 'created_at' => $cursor->toDateTimeString(), 'updated_at' => $cursor->toDateTimeString()];
        }

        if ($completado) {
            $cursor = $cursor->copy()->addHours(rand(1, 6))->addMinutes(rand(0, 59));
            $bulk[] = ['ticket_id' => $ticketId, 'status_id' => $statuses['completado']->id, 'created_at' => $cursor->toDateTimeString(), 'updated_at' => $cursor->toDateTimeString()];
        }
    }
}
