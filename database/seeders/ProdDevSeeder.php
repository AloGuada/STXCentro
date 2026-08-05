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
use App\Models\Prod\GrupoPrecioProceso;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Models\Prod\Ubicacion;
use App\Models\User;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\VersionadorCatalogo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Datos de ejemplo del módulo Producción para desarrollo/demo.
 *
 * Es idempotente: **borra** los datos del módulo y los vuelve a sembrar, así
 * que se puede correr tantas veces como se quiera. No toca `obras`, que son
 * compartidas con Costos y Cobranza.
 *
 * Lo que deja armado, pensado para ver cada regla del módulo en pantalla:
 *  - Catálogos versionados, incluida una obra con v2 (para el comparador).
 *  - Piezas sin precio, para que salte la advertencia antes de cerrar.
 *  - Una semana con pago parcial al 60%, que deja su saldo en "Pendientes por
 *    liquidar" de la semana siguiente.
 *  - Asistencia con faltas y vacaciones, que recortan el sueldo base.
 *  - Un grupo cuyo destajo no alcanza a cubrir las bases (delta negativo).
 *
 * Correr con: php artisan db:seed --class=ProdDevSeeder
 */
class ProdDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->limpiar();
        $this->call(ProdTipoSeeder::class);

        // Sin salario minimo el sueldo base saldria en cero y el reparto no se
        // podria leer en los datos de ejemplo.
        ConfiguracionProd::actual()->update(['salario_minimo_diario' => 300]);

        // La generación de liquidaciones usa auth()->id() para generado_por.
        $usuario = User::query()->first() ?? User::factory()->create();
        Auth::login($usuario);

        $categoriasEmpleado = $this->crearCategoriasEmpleado();
        $grupos = $this->crearGruposTrabajo($categoriasEmpleado);
        $piezasPorObra = $this->crearObrasConCatalogos();
        $tipos = TipoPagoExtra::all();

        $this->sembrarSemanas($grupos, $piezasPorObra, $tipos);

        Auth::logout();

        $this->command?->info('Producción sembrada: '.Destajo::count().' destajos, '
            .GrupoTrabajo::count().' grupos, '.Concepto::count().' piezas.');
    }

    /**
     * Borra lo del módulo en orden seguro de llaves foráneas.
     *
     * `obras` queda intacta a propósito: la comparten Costos y Cobranza y
     * borrarla se llevaría presupuestos y estimaciones ajenas a Producción.
     */
    private function limpiar(): void
    {
        foreach ([
            'prod_asistencias',
            'prod_liquidacion_empleados',
            'prod_liquidacion_detalle',
            'prod_liquidaciones',
            'prod_pagos_extra',
            'prod_registros',
            'prod_piezas',
            'prod_grupo_precio_conceptos',
            'prod_grupo_precio_procesos',
            'prod_grupos_precio',
            'prod_obra_procesos',
            'prod_grupo_empleados',
            'prod_grupo_trabajo_ubicaciones',
            'prod_grupos_trabajo',
            'prod_ubicaciones',
            'prod_categorias_empleado',
            'conceptos',
            'prod_catalogos',
            'prod_destajos',
        ] as $tabla) {
            DB::table($tabla)->delete();
        }
    }

    /**
     * El `valor` es un peso para repartir el excedente, no un sueldo.
     *
     * @return Collection<string, CategoriaEmpleado>
     */
    private function crearCategoriasEmpleado(): Collection
    {
        $data = ['Oficial' => 2000, 'Media cuchara' => 1500, 'Ayudante' => 1000, 'Aprendiz' => 700];

        $categorias = new Collection;
        $orden = 1;

        foreach ($data as $nombre => $valor) {
            $categorias[$nombre] = CategoriaEmpleado::create([
                'nombre' => $nombre,
                'valor' => $valor,
                'orden' => $orden++,
                'activo' => true,
            ]);
        }

        return $categorias;
    }

    /**
     * @param  Collection<string, CategoriaEmpleado>  $categorias
     * @return Collection<int, GrupoTrabajo>
     */
    private function crearGruposTrabajo(Collection $categorias): Collection
    {
        $data = [
            ['descripcion' => 'Cuadrilla A · Armado', 'ubicaciones' => ['Línea 1 · Módulo 1', 'Línea 1 · Módulo 2'], 'empleados' => [
                ['Juan Pérez', '1001', 'Oficial'], ['Luis Gómez', '1002', 'Media cuchara'],
                ['Mario Ruiz', '1003', 'Ayudante'], ['Iván Cruz', '1004', 'Aprendiz'],
            ]],
            ['descripcion' => 'Cuadrilla B · Soldadura', 'ubicaciones' => ['Línea 2 · Módulo 1'], 'empleados' => [
                ['Ana López', '2001', 'Oficial'], ['Rosa Díaz', '2002', 'Media cuchara'], ['Beto Sosa', '2003', 'Ayudante'],
            ]],
            ['descripcion' => 'Cuadrilla C · Habilitado', 'ubicaciones' => ['Patio de habilitado'], 'empleados' => [
                ['Pedro Sánchez', '3001', 'Oficial'], ['Sara Vega', '3002', 'Ayudante'],
            ]],
            ['descripcion' => 'Cuadrilla D · Pintura', 'ubicaciones' => ['Nave de pintura', 'Patio de habilitado'], 'empleados' => [
                ['Elena Ruiz', '4001', 'Media cuchara'], ['Hugo Marín', '4002', 'Ayudante'],
            ]],
        ];

        return new Collection(array_map(function (array $g) use ($categorias): GrupoTrabajo {
            $grupo = GrupoTrabajo::create(['descripcion' => $g['descripcion'], 'activo' => true]);

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
     * Tres obras con su catálogo. La primera estrena una v2 (renombra una marca
     * y cambia un peso) para poder ver el comparador de versiones, y la tercera
     * deja piezas sin precio para que salte la advertencia al cerrar.
     *
     * @return Collection<int, Collection<int, Concepto>>
     */
    private function crearObrasConCatalogos(): Collection
    {
        $obrasData = [
            ['no' => 'OBRA-101', 'descripcion' => 'Nave Industrial Norte', 'precioKilo' => 18.5, 'versionar' => true, 'sinPrecio' => 0, 'piezas' => [
                ['V-01', 'Viga IPR 12"', 85.5], ['V-02', 'Viga IPR 10"', 62.0],
                ['C-01', 'Columna HSS 8x8', 120.0], ['C-02', 'Columna HSS 6x6', 78.0],
                ['CN-01', 'Conexión atornillada', 12.4],
            ]],
            ['no' => 'OBRA-202', 'descripcion' => 'Puente Peatonal Sur', 'precioKilo' => 21.0, 'versionar' => false, 'sinPrecio' => 0, 'piezas' => [
                ['P-01', 'Placa base 20mm', 45.0], ['P-02', 'Placa unión 12mm', 28.0],
                ['A-01', 'Ángulo reforzado', 15.5], ['T-01', 'Tensor roscado', 8.2],
            ]],
            ['no' => 'OBRA-303', 'descripcion' => 'Ampliación Almacén Poniente', 'precioKilo' => 19.75, 'versionar' => false, 'sinPrecio' => 2, 'piezas' => [
                ['M-01', 'Montén C 8"', 22.0], ['M-02', 'Montén C 6"', 16.5],
                ['LR-01', 'Larguero de techo', 31.0], ['CT-01', 'Contraventeo', 9.8],
            ]],
        ];

        $categoriasPieza = collect(['Viga', 'Columna', 'Placa', 'Angulo', 'Conexion'])
            ->map(fn (string $nombre) => Categoria::firstOrCreate(['nombre' => $nombre]));

        $porObra = new Collection;

        foreach ($obrasData as $od) {
            $obra = Obra::firstOrCreate(
                ['no' => $od['no']],
                ['descripcion' => $od['descripcion'], 'presupuesto_total' => 5000000, 'estatus' => 'abierta'],
            );

            $catalogo = Catalogo::create([
                'obra_id' => $obra->id,
                'nombre' => 'Catálogo '.$obra->no,
                'version' => 1,
                'vigente' => true,
            ]);

            // La obra paga los dos procesos, cada uno con su tarifa: pintar
            // vale bastante menos que soldar.
            $obra->procesos()->sync($this->procesos()->pluck('id'));

            $grupoPrecio = GrupoPrecio::create([
                'obra_id' => $obra->id,
                'descripcion' => 'Precio base',
            ]);

            foreach ($this->procesos() as $proceso) {
                GrupoPrecioProceso::create([
                    'grupo_precio_id' => $grupoPrecio->id,
                    'proceso_id' => $proceso->id,
                    'precio_kilo' => $proceso->nombre === 'Pintura'
                        ? round($od['precioKilo'] * 0.35, 4)
                        : $od['precioKilo'],
                ]);
            }

            $piezas = new Collection;

            foreach ($od['piezas'] as $indice => [$marca, $desc, $peso]) {
                $pieza = Concepto::create([
                    'obra_id' => $obra->id,
                    'catalogo_id' => $catalogo->id,
                    'marca' => $marca,
                    'descripcion' => $desc,
                    // Holgado a proposito: el tope del catalogo no debe estorbar
                    // al capturar produccion de ejemplo.
                    'cantidad' => 6,
                    'peso_unitario' => $peso,
                    'longitud' => fake()->numberBetween(500, 12000),
                    'categoria_id' => $categoriasPieza->random()->id,
                    'version' => 1,
                    'activo' => true,
                ]);

                $this->sembrarPiezas($pieza);

                // Las ultimas N piezas quedan sin grupo de precio, para que la
                // pantalla del destajo advierta que se pagarian en $0.
                if ($indice < count($od['piezas']) - $od['sinPrecio']) {
                    GrupoPrecioConcepto::create([
                        'grupo_precio_id' => $grupoPrecio->id,
                        'concepto_id' => $pieza->id,
                    ]);
                }

                $piezas->push($pieza);
            }

            if ($od['versionar']) {
                $piezas = $this->versionar($catalogo);
            }

            $porObra->push($piezas);
        }

        return $porObra;
    }

    /**
     * Crea la v2 del catálogo con cambios reales (una marca renombrada, un peso
     * distinto y una pieza nueva) para que el comparador tenga qué mostrar.
     *
     * @return Collection<int, Concepto>
     */
    private function versionar(Catalogo $catalogo): Collection
    {
        $v2 = app(VersionadorCatalogo::class)->nuevaVersion(
            $catalogo,
            'Reemisión de planos: se ajustó el peso de la viga principal.'
        );

        $v2->conceptos()->where('marca', 'V-02')->update(['marca' => 'V-02R', 'peso_unitario' => 68.0]);

        $nueva = Concepto::create([
            'obra_id' => $v2->obra_id,
            'catalogo_id' => $v2->id,
            'marca' => 'CN-02',
            'descripcion' => 'Conexión soldada (nueva en v2)',
            'cantidad' => 150,
            'peso_unitario' => 14.0,
            'longitud' => 600,
            'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Conexion'])->id,
            'version' => 2,
            'activo' => true,
        ]);

        $this->sembrarPiezas($nueva);

        $grupoPrecio = GrupoPrecio::where('obra_id', $v2->obra_id)->firstOrFail();
        GrupoPrecioConcepto::create(['grupo_precio_id' => $grupoPrecio->id, 'concepto_id' => $nueva->id]);

        return $v2->conceptos()->get();
    }

    /**
     * Tres semanas encadenadas: dos cerradas y la actual abierta, con un pago
     * parcial que deja saldo pendiente por liquidar.
     *
     * @param  Collection<int, GrupoTrabajo>  $grupos
     * @param  Collection<int, Collection<int, Concepto>>  $piezasPorObra
     * @param  Collection<int, TipoPagoExtra>  $tipos
     */
    private function sembrarSemanas(Collection $grupos, Collection $piezasPorObra, Collection $tipos): void
    {
        $generador = app(GeneradorLiquidaciones::class);

        // Semana -2: cerrada, todo al 100%.
        $semana2 = $this->crearDestajo(2);
        $this->poblarProduccion($semana2, $grupos, $piezasPorObra, $tipos);
        $generador->generar($semana2);

        // Semana -1: cerrada, con un lote pagado al 60% que deja saldo.
        $semana1 = $this->crearDestajo(1);
        $this->poblarProduccion($semana1, $grupos, $piezasPorObra, $tipos);
        $this->parcialidad($semana1, $grupos->first(), $piezasPorObra, 60);
        $generador->generar($semana1);

        // Semana actual: abierta. Trae el 40% restante como pendiente y un
        // grupo con poca producción para ver el caso de delta negativo.
        $actual = $this->crearDestajo(0);
        $this->poblarProduccion($actual, $grupos, $piezasPorObra, $tipos);
        $this->grupoConDeltaNegativo($actual, $grupos->last(), $piezasPorObra->last()->first());
    }

    private function crearDestajo(int $semanasAtras): Destajo
    {
        $inicio = Carbon::now()->startOfWeek()->subWeeks($semanasAtras);

        return Destajo::create([
            'anio' => $inicio->year,
            'semana' => min($inicio->weekOfYear, 52),
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDays(6)->toDateString(),
        ]);
    }

    /** Un lote pagado a medias: su saldo aparecerá en la semana siguiente. */
    private function parcialidad(Destajo $destajo, GrupoTrabajo $grupo, Collection $piezasPorObra, float $porcentaje): void
    {
        // La primera marca que aun tenga piezas sin pagar: buscar a ciegas
        // dejaria el caso sin sembrar en cuanto la produccion se las coma.
        $piezas = $piezasPorObra
            ->flatten()
            ->map(fn (Concepto $marca) => $this->piezasLibres($marca, 3))
            ->first(fn (Collection $libres) => $libres->isNotEmpty()) ?? collect();

        foreach ($piezas as $pieza) {
            Registro::create([
                'fecha' => Carbon::parse($destajo->fecha_inicio)->addDay()->toDateString(),
                'pieza_id' => $pieza->id,
                'proceso_id' => $this->procesos()->first()->id,
                'grupo_trabajo_id' => $grupo->id,
                'porcentaje' => $porcentaje,
            ]);
        }
    }

    /**
     * Grupo cuya producción no alcanza a cubrir el sueldo base de la semana:
     * cada quien conserva su base y no hay excedente que repartir.
     */
    private function grupoConDeltaNegativo(Destajo $destajo, GrupoTrabajo $grupo, Concepto $marca): void
    {
        foreach ($this->piezasLibres($marca, 1) as $pieza) {
            Registro::create([
                'fecha' => Carbon::parse($destajo->fecha_inicio)->addDay()->toDateString(),
                'pieza_id' => $pieza->id,
                'proceso_id' => $this->procesos()->first()->id,
                'grupo_trabajo_id' => $grupo->id,
                'porcentaje' => 100,
            ]);
        }
    }

    /**
     * La asistencia es obligatoria para cerrar, así que se siembra completa;
     * con faltas y vacaciones sueltas para que el sueldo base no salga parejo.
     */
    private function poblarAsistencia(Destajo $destajo, GrupoTrabajo $grupo): void
    {
        foreach ($grupo->empleados as $empleado) {
            $cursor = Carbon::parse($destajo->fecha_inicio);
            $fin = Carbon::parse($destajo->fecha_fin);

            while ($cursor->lte($fin)) {
                $estado = match (true) {
                    $cursor->isSunday() => EstadoAsistencia::NoAplica,
                    fake()->boolean(8) => EstadoAsistencia::Falta,
                    fake()->boolean(4) => EstadoAsistencia::Vacaciones,
                    default => EstadoAsistencia::Asistencia,
                };

                Asistencia::updateOrCreate(
                    ['grupo_empleado_id' => $empleado->id, 'fecha' => $cursor->copy()->startOfDay()],
                    ['destajo_id' => $destajo->id, 'estado' => $estado],
                );

                $cursor = $cursor->addDay();
            }
        }
    }

    /**
     * @param  Collection<int, GrupoTrabajo>  $grupos
     * @param  Collection<int, Collection<int, Concepto>>  $piezasPorObra
     * @param  Collection<int, TipoPagoExtra>  $tipos
     */
    private function poblarProduccion(Destajo $destajo, Collection $grupos, Collection $piezasPorObra, Collection $tipos): void
    {
        foreach ($grupos as $indice => $grupo) {
            $this->poblarAsistencia($destajo, $grupo);

            // Cada cuadrilla trabaja piezas de una obra distinta, rotando.
            $piezas = $piezasPorObra[$indice % $piezasPorObra->count()];

            $proceso = $this->procesos()->first();

            foreach ($piezas->random(min(3, $piezas->count())) as $marca) {
                // Un renglon por QS: se toman las que aun no se han pagado en
                // ese proceso, para no chocar con el tope.
                foreach ($this->piezasLibres($marca, fake()->numberBetween(2, 4), $proceso) as $pieza) {
                    Registro::create([
                        'fecha' => Carbon::parse($destajo->fecha_inicio)->addDays(fake()->numberBetween(0, 4))->toDateString(),
                        'pieza_id' => $pieza->id,
                        'proceso_id' => $proceso->id,
                        'grupo_trabajo_id' => $grupo->id,
                        'porcentaje' => 100,
                    ]);
                }
            }

            if ($tipos->isNotEmpty() && fake()->boolean(60)) {
                PagoExtra::create([
                    'descripcion' => fake()->randomElement(['Bono de la semana', 'Apoyo de transporte', 'Tiempo extra sábado']),
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

    /**
     * Los procesos del catálogo, cacheados: se consultan en cada renglón.
     *
     * @return Collection<int, Proceso>
     */
    private function procesos(): Collection
    {
        return $this->procesos ??= Proceso::activos()->orderBy('orden')->get();
    }

    /** @var Collection<int, Proceso>|null */
    private ?Collection $procesos = null;

    /**
     * Las unidades físicas de la marca, como las trae el layout: un QS por cada
     * pieza que pide el modelo.
     */
    private function sembrarPiezas(Concepto $marca): void
    {
        for ($i = 1; $i <= $marca->cantidad; $i++) {
            Pieza::create([
                'catalogo_id' => $marca->catalogo_id,
                'concepto_id' => $marca->id,
                'qs' => $marca->marca.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'activo' => true,
            ]);
        }
    }

    /**
     * Piezas de la marca que todavía no se han pagado en ese proceso. Sembrar
     * sin mirar el tope dejaría datos que la propia app rechazaría.
     *
     * @return Collection<int, Pieza>
     */
    private function piezasLibres(Concepto $marca, int $cuantas, ?Proceso $proceso = null): Collection
    {
        $proceso ??= $this->procesos()->first();

        return $marca->piezas()
            ->whereDoesntHave('registros', fn ($q) => $q->where('proceso_id', $proceso->id))
            ->limit($cuantas)
            ->get();
    }
}
