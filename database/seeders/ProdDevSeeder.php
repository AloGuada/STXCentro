<?php

namespace Database\Seeders;

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Categoria;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de ejemplo del módulo Producción para desarrollo/demo.
 *
 * Crea grupos de trabajo con empleados, obras con piezas y grupos de precio,
 * un destajo abierto (semana actual) con producción y pagos extra, y un
 * destajo cerrado (semana pasada) con sus liquidaciones ya generadas.
 *
 * Correr con: php artisan db:seed --class=ProdDevSeeder
 */
class ProdDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProdTipoSeeder::class);

        // La generación de liquidaciones usa auth()->id() para generado_por.
        $usuario = User::query()->first() ?? User::factory()->create();
        Auth::login($usuario);

        $grupos = $this->crearGruposTrabajo();
        $conceptos = $this->crearObrasConPiezas();
        $tipos = TipoPagoExtra::all();

        // Destajo cerrado: semana pasada, con liquidaciones generadas.
        $inicioCerrado = Carbon::now()->startOfWeek()->subWeek();
        $destajoCerrado = Destajo::create([
            'anio' => $inicioCerrado->year,
            'semana' => min($inicioCerrado->weekOfYear, 52),
            'fecha_inicio' => $inicioCerrado->toDateString(),
            'fecha_fin' => $inicioCerrado->copy()->addDays(6)->toDateString(),
        ]);
        $this->poblarProduccion($destajoCerrado, $grupos, $conceptos, $tipos);
        app(GeneradorLiquidaciones::class)->generar($destajoCerrado);

        // Destajo abierto: semana actual, listo para capturar/cerrar.
        $inicioAbierto = Carbon::now()->startOfWeek();
        $destajoAbierto = Destajo::create([
            'anio' => $inicioAbierto->year,
            'semana' => min($inicioAbierto->weekOfYear, 52),
            'fecha_inicio' => $inicioAbierto->toDateString(),
            'fecha_fin' => $inicioAbierto->copy()->addDays(6)->toDateString(),
        ]);
        $this->poblarProduccion($destajoAbierto, $grupos, $conceptos, $tipos);

        Auth::logout();
    }

    /**
     * @return Collection<int, GrupoTrabajo>
     */
    private function crearGruposTrabajo(): Collection
    {
        $data = [
            ['descripcion' => 'Cuadrilla A', 'linea' => 1, 'modulo' => 1, 'empleados' => [['Juan Pérez', '1001', 40], ['Luis Gómez', '1002', 35], ['Mario Ruiz', '1003', 25]]],
            ['descripcion' => 'Cuadrilla B', 'linea' => 1, 'modulo' => 2, 'empleados' => [['Ana López', '2001', 50], ['Rosa Díaz', '2002', 50]]],
            ['descripcion' => 'Cuadrilla C', 'linea' => 2, 'modulo' => 1, 'empleados' => [['Pedro Sánchez', '3001', 60], ['Sara Vega', '3002', 40]]],
        ];

        return new Collection(array_map(function (array $g): GrupoTrabajo {
            $grupo = GrupoTrabajo::create([
                'descripcion' => $g['descripcion'],
                'linea' => $g['linea'],
                'modulo' => $g['modulo'],
                'activo' => true,
            ]);

            foreach ($g['empleados'] as [$nombre, $no, $pct]) {
                $grupo->empleados()->create(['nombre' => $nombre, 'no_empleado' => $no, 'porcentaje' => $pct]);
            }

            return $grupo;
        }, $data));
    }

    /**
     * @return Collection<int, Concepto>
     */
    private function crearObrasConPiezas(): Collection
    {
        $obrasData = [
            ['no' => 'OBRA-101', 'descripcion' => 'Nave Industrial Norte', 'precioKilo' => 18.5, 'piezas' => [
                ['V-01', 'Viga IPR 12"', 85.5], ['V-02', 'Viga IPR 10"', 62.0],
                ['C-01', 'Columna HSS 8x8', 120.0], ['C-02', 'Columna HSS 6x6', 78.0],
            ]],
            ['no' => 'OBRA-202', 'descripcion' => 'Puente Peatonal Sur', 'precioKilo' => 21.0, 'piezas' => [
                ['P-01', 'Placa base 20mm', 45.0], ['P-02', 'Placa unión 12mm', 28.0], ['A-01', 'Ángulo reforzado', 15.5],
            ]],
        ];

        $categorias = collect(['Viga', 'Columna', 'Placa', 'Angulo', 'Conexion'])
            ->map(fn ($nombre) => Categoria::firstOrCreate(['nombre' => $nombre]));

        $conceptos = new Collection;

        foreach ($obrasData as $od) {
            $obra = Obra::firstOrCreate(
                ['no' => $od['no']],
                ['descripcion' => $od['descripcion'], 'presupuesto_total' => 5000000, 'estatus' => 'abierta'],
            );

            $grupoPrecio = GrupoPrecio::create([
                'obra_id' => $obra->id,
                'descripcion' => 'Precio base',
                'precio_kilo' => $od['precioKilo'],
            ]);

            foreach ($od['piezas'] as [$marca, $desc, $peso]) {
                $concepto = Concepto::create([
                    'obra_id' => $obra->id,
                    'marca' => $marca,
                    'descripcion' => $desc,
                    'cantidad' => fake()->numberBetween(10, 60),
                    'peso_unitario' => $peso,
                    'longitud' => fake()->numberBetween(500, 12000),
                    'categoria_id' => $categorias->random()->id,
                    'version' => 1,
                    'activo' => true,
                ]);

                GrupoPrecioConcepto::create(['grupo_precio_id' => $grupoPrecio->id, 'concepto_id' => $concepto->id]);
                $conceptos->push($concepto);
            }
        }

        return $conceptos;
    }

    /**
     * @param  Collection<int, GrupoTrabajo>  $grupos
     * @param  Collection<int, Concepto>  $conceptos
     * @param  Collection<int, TipoPagoExtra>  $tipos
     */
    private function poblarProduccion(Destajo $destajo, Collection $grupos, Collection $conceptos, Collection $tipos): void
    {
        foreach ($grupos as $grupo) {
            foreach ($conceptos->random(min(3, $conceptos->count())) as $concepto) {
                Registro::create([
                    'fecha' => Carbon::parse($destajo->fecha_inicio)->addDays(fake()->numberBetween(0, 4))->toDateString(),
                    'concepto_id' => $concepto->id,
                    'grupo_trabajo_id' => $grupo->id,
                    'cantidad' => fake()->numberBetween(3, 25),
                ]);
            }

            if ($tipos->isNotEmpty() && fake()->boolean(60)) {
                PagoExtra::create([
                    'descripcion' => 'Bono de la semana',
                    'tipo_id' => $tipos->random()->id,
                    'destajo_id' => $destajo->id,
                    'grupo_trabajo_id' => $grupo->id,
                    'precio' => fake()->randomElement([100, 150, 200]),
                    'dias' => fake()->numberBetween(1, 5),
                    'personas' => $grupo->empleados()->count(),
                ]);
            }
        }
    }
}
