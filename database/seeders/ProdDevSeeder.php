<?php

namespace Database\Seeders;

use App\Enums\Prod\EstadoAsistencia;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\ConfiguracionProd;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\Prod\Ubicacion;
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

        // Sin salario minimo el sueldo base saldria en cero y el reparto no se
        // podria leer en los datos de ejemplo.
        ConfiguracionProd::actual()->update(['salario_minimo_diario' => 300]);

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
            ['descripcion' => 'Cuadrilla A', 'ubicaciones' => ['Línea 1 · Módulo 1'], 'empleados' => [['Juan Pérez', '1001', 'Oficial'], ['Luis Gómez', '1002', 'Media cuchara'], ['Mario Ruiz', '1003', 'Ayudante']]],
            ['descripcion' => 'Cuadrilla B', 'ubicaciones' => ['Línea 1 · Módulo 2', 'Patio'], 'empleados' => [['Ana López', '2001', 'Oficial'], ['Rosa Díaz', '2002', 'Ayudante']]],
            ['descripcion' => 'Cuadrilla C', 'ubicaciones' => ['Línea 2 · Módulo 1'], 'empleados' => [['Pedro Sánchez', '3001', 'Oficial'], ['Sara Vega', '3002', 'Media cuchara']]],
        ];

        $categorias = collect(['Oficial' => 2000, 'Media cuchara' => 1500, 'Ayudante' => 1000])
            ->map(fn (int $valor, string $nombre) => CategoriaEmpleado::firstOrCreate(
                ['nombre' => $nombre],
                ['valor' => $valor, 'activo' => true],
            ));

        return new Collection(array_map(function (array $g) use ($categorias): GrupoTrabajo {
            $grupo = GrupoTrabajo::create([
                'descripcion' => $g['descripcion'],
                'activo' => true,
            ]);

            $grupo->ubicaciones()->sync(
                collect($g['ubicaciones'])
                    ->map(fn (string $nombre) => Ubicacion::firstOrCreate(['nombre' => $nombre], ['activo' => true])->id)
                    ->all()
            );

            foreach ($g['empleados'] as [$nombre, $no, $categoria]) {
                $grupo->empleados()->create([
                    'nombre' => $nombre,
                    'no_empleado' => $no,
                    'categoria_empleado_id' => $categorias[$categoria]->id,
                ]);
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

            $catalogo = Catalogo::firstOrCreate(
                ['obra_id' => $obra->id, 'version' => 1],
                ['nombre' => 'Catálogo '.$obra->no, 'vigente' => true],
            );

            foreach ($od['piezas'] as [$marca, $desc, $peso]) {
                $concepto = Concepto::create([
                    'obra_id' => $obra->id,
                    'catalogo_id' => $catalogo->id,
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
     * La asistencia es obligatoria para cerrar el destajo, asi que los datos de
     * ejemplo la traen completa (con alguna falta suelta para que se note).
     */
    private function poblarAsistencia(Destajo $destajo, GrupoTrabajo $grupo): void
    {
        foreach ($grupo->empleados as $empleado) {
            $cursor = Carbon::parse($destajo->fecha_inicio);
            $fin = Carbon::parse($destajo->fecha_fin);

            while ($cursor->lte($fin)) {
                Asistencia::updateOrCreate(
                    ['grupo_empleado_id' => $empleado->id, 'fecha' => $cursor->copy()->startOfDay()],
                    [
                        'destajo_id' => $destajo->id,
                        'estado' => fake()->boolean(90)
                            ? EstadoAsistencia::Asistencia
                            : fake()->randomElement([EstadoAsistencia::Falta, EstadoAsistencia::Vacaciones]),
                    ],
                );

                $cursor = $cursor->addDay();
            }
        }
    }

    /**
     * @param  Collection<int, GrupoTrabajo>  $grupos
     * @param  Collection<int, Concepto>  $conceptos
     * @param  Collection<int, TipoPagoExtra>  $tipos
     */
    private function poblarProduccion(Destajo $destajo, Collection $grupos, Collection $conceptos, Collection $tipos): void
    {
        foreach ($grupos as $grupo) {
            $this->poblarAsistencia($destajo, $grupo);

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
