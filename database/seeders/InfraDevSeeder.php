<?php

namespace Database\Seeders;

use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Ptar;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;
use App\Models\Infra\Turno;
use App\Models\Infra\TurnoDia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InfraDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->limpiarDatos();

        $turnos = $this->crearTurnos();
        $usuario = User::first();

        // Generar 12 meses de lecturas diarias (lunes a sábado)
        for ($m = 11; $m >= 0; $m--) {
            $inicio = Carbon::now()->subMonths($m)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();

            // Estado acumulado para compresores (horas y kwhr incrementales)
            $compresorAcum = [
                1 => ['marcha' => random_int(800, 1200), 'kwhr' => random_int(5000, 8000)],
                2 => ['marcha' => random_int(800, 1200), 'kwhr' => random_int(5000, 8000)],
                3 => ['marcha' => random_int(600, 900), 'kwhr' => random_int(4000, 6000)],
            ];

            // Estado acumulado para tanques (kg que van bajando)
            $tanqueKg = [
                'oxigeno' => fake()->randomFloat(2, 3500, 4500),
                'argon' => fake()->randomFloat(2, 3000, 4000),
                'co2' => fake()->randomFloat(2, 2500, 3500),
                'lp' => fake()->randomFloat(2, 3000, 4000),
            ];

            // Lecturas acumulativas del transformador
            $transAcum = [
                'total_1' => random_int(50000, 80000),
                'total_5' => random_int(30000, 50000),
            ];

            $dia = $inicio->copy();
            while ($dia->lte($fin)) {
                // Saltar domingos
                if ($dia->dayOfWeek === Carbon::SUNDAY) {
                    $dia->addDay();

                    continue;
                }

                foreach ($turnos as $turno) {
                    $fecha = $dia->copy()->setTimeFromTimeString($turno->hora_inicio);

                    $compresorAcum = $this->crearCompresor($usuario->id, $turno->id, $fecha, $compresorAcum);
                    $this->crearBomba($usuario->id, $turno->id, $fecha);
                    $transAcum = $this->crearTransformador($usuario->id, $turno->id, $fecha, $transAcum);
                    $tanqueKg = $this->crearTanque($usuario->id, $turno->id, $fecha, $tanqueKg);
                    $this->crearPtar($usuario->id, $turno->id, $fecha);
                }

                $dia->addDay();
            }
        }
    }

    private function limpiarDatos(): void
    {
        DB::table('infra_compresores')->delete();
        DB::table('infra_bombas')->delete();
        DB::table('infra_transformadores')->delete();
        DB::table('infra_tanques')->delete();
        DB::table('infra_ptar')->delete();
        DB::table('infra_turnos_dia')->delete();
        DB::table('infra_turnos')->delete();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Turno>
     */
    private function crearTurnos(): \Illuminate\Support\Collection
    {
        $definiciones = [
            ['nombre' => 'Matutino', 'hora_inicio' => '06:00', 'hora_fin' => '14:00', 'orden' => 1],
            ['nombre' => 'Vespertino', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'orden' => 2],
            ['nombre' => 'Nocturno', 'hora_inicio' => '22:00', 'hora_fin' => '06:00', 'orden' => 3],
        ];

        $turnos = collect();
        foreach ($definiciones as $def) {
            $turno = Turno::create([...$def, 'activo' => true]);

            // Lunes a sábado (1-6)
            for ($d = 1; $d <= 6; $d++) {
                TurnoDia::create(['infra_turno_id' => $turno->id, 'dia_semana' => $d]);
            }

            $turnos->push($turno);
        }

        return $turnos;
    }

    /**
     * @param  array<int, array{marcha: int|float, kwhr: int|float}>  $acum
     * @return array<int, array{marcha: int|float, kwhr: int|float}>
     */
    private function crearCompresor(string $usuarioId, int $turnoId, Carbon $fecha, array $acum): array
    {
        $data = ['usuario_id' => $usuarioId, 'infra_turno_id' => $turnoId];

        foreach ([1, 2, 3] as $i) {
            $ok = fake()->boolean(85);
            $data["compresor_{$i}_status"] = $ok;
            // Rango verde: 116-124 PSI; 85% dentro de rango
            $data["compresor_{$i}_presion_aire"] = $ok
                ? fake()->randomFloat(2, 116, 124)
                : fake()->randomFloat(2, 100, 115);
            $data["compresor_{$i}_tiempo_trabajo"] = fake()->randomFloat(2, 4, 8);

            // Horas de marcha incrementales
            $acum[$i]['marcha'] += fake()->randomFloat(2, 4, 8);
            $data["compresor_{$i}_tiempo_marcha"] = round($acum[$i]['marcha'], 2);

            $acum[$i]['kwhr'] += fake()->randomFloat(2, 50, 150);
            $data["compresor_{$i}_kwhr"] = round($acum[$i]['kwhr'], 2);
        }

        $data['observaciones'] = fake()->optional(0.1)->sentence();

        Compresor::create([...$data, 'created_at' => $fecha, 'updated_at' => $fecha]);

        return $acum;
    }

    private function crearBomba(string $usuarioId, int $turnoId, Carbon $fecha): void
    {
        // 85% de las veces todo en rango verde
        $normal = fake()->boolean(85);

        Bomba::create([
            'usuario_id' => $usuarioId,
            'infra_turno_id' => $turnoId,
            'bomba_posos_1' => $normal ? true : fake()->boolean(70),
            'bomba_posos_2' => $normal ? true : fake()->boolean(70),
            'bomba_planta_1' => $normal ? true : fake()->boolean(80),
            'bomba_planta_2' => $normal ? true : fake()->boolean(80),
            'bomba_planta_3' => $normal ? true : fake()->boolean(80),
            'nivel_salmuera' => $normal
                ? fake()->randomFloat(2, 40, 90)
                : fake()->randomFloat(2, 5, 100),
            'nivel_tinaco' => $normal
                ? fake()->randomFloat(2, 50, 95)
                : fake()->randomFloat(2, 10, 100),
            'nivel_sisterna' => $normal
                ? fake()->randomFloat(2, 50, 95)
                : fake()->randomFloat(2, 10, 100),
            'presion_tuberia' => $normal
                ? fake()->randomFloat(2, 42, 48)
                : fake()->randomFloat(2, 30, 55),
            'nivel_hipoclorito' => $normal
                ? fake()->randomFloat(2, 30, 80)
                : fake()->randomFloat(2, 0, 100),
            'nivel_anticongelante' => fake()->randomFloat(2, 40, 90),
            'aceite_del_motor' => fake()->randomFloat(2, 60, 95),
            'tanque_diesel' => fake()->randomFloat(2, 40, 90),
            'voltaje_bateria' => fake()->randomFloat(2, 11.5, 13.5),
            'bomba_jockey' => $normal ? true : fake()->boolean(80),
            'bomba_electrica' => fake()->boolean(90),
            'bomba_diesel' => fake()->boolean(50),
            'presion_tuberia_incendio' => fake()->randomFloat(2, 40, 55),
            'observaciones' => fake()->optional(0.1)->sentence(),
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
    }

    /**
     * @param  array{total_1: int|float, total_5: int|float}  $acum
     * @return array{total_1: int|float, total_5: int|float}
     */
    private function crearTransformador(string $usuarioId, int $turnoId, Carbon $fecha, array $acum): array
    {
        // Lecturas acumulativas
        $acum['total_1'] += fake()->randomFloat(2, 100, 400);
        $acum['total_5'] += fake()->randomFloat(2, 80, 300);

        Transformador::create([
            'usuario_id' => $usuarioId,
            'infra_turno_id' => $turnoId,
            'linea_A' => fake()->randomFloat(2, 250, 400),
            'linea_A_max' => fake()->randomFloat(2, 420, 520),
            'date_A' => $fecha,
            'linea_B' => fake()->randomFloat(2, 250, 400),
            'linea_B_max' => fake()->randomFloat(2, 420, 520),
            'date_B' => $fecha,
            'linea_C' => fake()->randomFloat(2, 250, 400),
            'linea_C_max' => fake()->randomFloat(2, 420, 520),
            'date_C' => $fecha,
            'total_1' => round($acum['total_1'], 2),
            'total_5' => round($acum['total_5'], 2),
            'lectura_5y5' => fake()->randomFloat(2, 200, 600),
            'lectura_301' => fake()->randomFloat(2, 100, 400),
            'lectura_302' => fake()->randomFloat(2, 100, 400),
            'lectura_303' => fake()->randomFloat(2, 100, 400),
            'lectura_310' => fake()->randomFloat(2, 50, 250),
            'observaciones' => fake()->optional(0.1)->sentence(),
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        return $acum;
    }

    /**
     * @param  array{oxigeno: float, argon: float, co2: float, lp: float}  $kg
     * @return array{oxigeno: float, argon: float, co2: float, lp: float}
     */
    private function crearTanque(string $usuarioId, int $turnoId, Carbon $fecha, array $kg): array
    {
        // Consumo gradual con recargas cuando baja mucho
        foreach (['oxigeno', 'argon', 'co2', 'lp'] as $gas) {
            $kg[$gas] -= fake()->randomFloat(2, 20, 80);

            // Recarga si baja de 500 kg
            if ($kg[$gas] < 500) {
                $kg[$gas] = fake()->randomFloat(2, 3500, 4500);
            }
        }

        // 85% presiones en rango verde
        $normal = fake()->boolean(85);

        Tanque::create([
            'usuario_id' => $usuarioId,
            'infra_turno_id' => $turnoId,
            'pa_sistema_oxigeno' => fake()->randomFloat(2, 180, 260),
            'presion_sistema_oxigeno' => fake()->randomFloat(2, 200, 250),
            'presion_tanque_oxigeno' => $normal
                ? fake()->randomFloat(2, 210, 245)
                : fake()->randomFloat(2, 150, 260),
            'lt_tanque_oxigeno' => fake()->randomFloat(2, 2000, 8000),
            'kg_tanque_oxigeno' => round($kg['oxigeno'], 2),
            'pa_sistema_argon' => fake()->randomFloat(2, 180, 260),
            'presion_sistema_argon' => fake()->randomFloat(2, 200, 250),
            'presion_tanque_argon' => $normal
                ? fake()->randomFloat(2, 210, 245)
                : fake()->randomFloat(2, 150, 260),
            'lt_tanque_argon' => fake()->randomFloat(2, 2000, 8000),
            'kg_tanque_argon' => round($kg['argon'], 2),
            'pa_sistema_co2' => fake()->randomFloat(2, 180, 290),
            'presion_sistema_co2' => $normal
                ? fake()->randomFloat(2, 210, 280)
                : fake()->randomFloat(2, 150, 300),
            'presion_tanque_co2' => fake()->randomFloat(2, 200, 280),
            'lt_tanque_co2' => fake()->randomFloat(2, 2000, 8000),
            'kg_tanque_co2' => round($kg['co2'], 2),
            'pa_sistema_lp' => fake()->randomFloat(2, 180, 260),
            'presion_sistema_lp' => fake()->randomFloat(2, 200, 250),
            'presion_tanque_lp' => $normal
                ? fake()->randomFloat(2, 210, 245)
                : fake()->randomFloat(2, 150, 260),
            'numero_tanque_lp' => fake()->numberBetween(1, 5),
            'lt_tanque_lp' => fake()->randomFloat(2, 2000, 8000),
            'kg_tanque_lp' => round($kg['lp'], 2),
            'observaciones' => fake()->optional(0.1)->sentence(),
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        return $kg;
    }

    private function crearPtar(string $usuarioId, int $turnoId, Carbon $fecha): void
    {
        $normal = fake()->boolean(85);

        Ptar::create([
            'usuario_id' => $usuarioId,
            'infra_turno_id' => $turnoId,
            'soplador_activa' => $normal ? true : fake()->boolean(70),
            'bomba_activa' => $normal ? true : fake()->boolean(70),
            'trampa_solida' => $normal ? true : fake()->boolean(60),
            'nivel_cloro' => $normal
                ? fake()->randomFloat(2, 40, 85)
                : fake()->randomFloat(2, 5, 100),
            'observaciones' => fake()->optional(0.1)->sentence(),
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
    }
}
