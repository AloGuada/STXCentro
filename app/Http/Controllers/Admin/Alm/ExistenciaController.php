<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Exports\Alm\ExistenciasExport;
use App\Http\Controllers\Controller;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Models\Obra;
use App\Services\Alm\SaldoEnTransito;
use App\Support\HoraLocal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La pantalla de diario: qué hay y cuánto en cada almacén.
 *
 * Se abre en blanco: el consolidado de la empresa son decenas de miles de
 * renglones que nadie lee, así que hasta que no se elige un almacén, una obra,
 * una ubicación o se teclea algo en el buscador no se consulta nada. Con filtro
 * es el inventario de esa bodega. Es sólo lectura — lo único que se corrige
 * desde aquí es dónde está acomodado el material, y eso vive en
 * `UbicacionController` porque es una decisión sobre el lugar, no sobre el
 * saldo.
 */
class ExistenciaController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(private readonly SaldoEnTransito $transito) {}

    public function index(Request $request): Response
    {
        $almacenId = $request->integer('almacen_id') ?: null;

        $comunes = [
            'filters' => $request->only(['almacen_id', 'ubicacion_id', 'obra_id', 'area_id', 'search', 'sin_acomodar', 'saldo']),
            'almacenes' => $this->almacenes($request),
            // Sólo las activas: filtrar por un área muerta no devuelve nada y
            // ensucia la lista de la que hay que elegir.
            'areas' => Area::query()->activas()->orderBy('descripcion')->get(['id', 'descripcion']),
            // Para el filtro por obra y para el modal de reasignación.
            'obras' => Obra::query()->orderBy('no')->get(['id', 'no']),
            'puedeReasignar' => $request->user()?->can('alm.asignaciones.reasignar') ?? false,
        ];

        // Sin pregunta no hay respuesta: ni renglones ni totales. Los totales
        // viajan en `null` para que la vista sepa que no es un inventario vacío,
        // es un inventario que todavía no se ha consultado.
        if (! $this->hayFiltro($request)) {
            return Inertia::render('admin/almacen/existencias/index', [
                ...$comunes,
                'existencias' => new LengthAwarePaginator([], 0, self::POR_PAGINA),
                'totales' => null,
                'ubicaciones' => [],
            ]);
        }

        $visibles = $this->almacenesVisibles($request);
        $filtrada = $this->consultaFiltrada($request, $visibles);

        // El pie de la tabla suma lo filtrado entero, no la página: un total que
        // cambia al pasar de página no significa nada.
        $totales = [
            'renglones' => (clone $filtrada)->count(),
            'valor' => (float) (clone $filtrada)->sum('valor'),
        ];

        $existencias = $filtrada
            ->with([
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'articulo:id,codigo,descripcion,unidad,tipo,stock_minimo,se_controla_por_pieza,clasificacion_abc,area_id',
                'articulo.area:id,descripcion',
                'ubicacion.padre',
                // El desglose por obra viaja con la fila: son pocas por renglón
                // y pedirlo aparte sería una consulta por existencia.
                'asignaciones.obra:id,no',
            ])
            ->join('alm_articulos', 'alm_articulos.id', '=', 'alm_existencias.articulo_id')
            ->orderBy('alm_articulos.descripcion')
            ->select('alm_existencias.*')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $piezas = $this->desglosePiezas($existencias->getCollection());
        // Lo que viene hacia acá y todavía no es de nadie. Va en columna aparte
        // y **no** entra al valor del inventario: no es de esta bodega todavía.
        $enTransito = $almacenId === null ? [] : $this->transito->haciaAlmacen($almacenId);

        $existencias->through(fn (Existencia $e): array => [
            'id' => $e->id,
            'almacen_id' => $e->almacen_id,
            'almacen' => $e->almacen?->clave,
            'obra' => $e->almacen?->obra?->no,
            'articulo_id' => $e->articulo_id,
            'codigo' => $e->articulo?->codigo,
            'descripcion' => $e->articulo?->descripcion,
            'unidad' => $e->articulo?->unidad,
            // Si se gasta o si sale y regresa: con eso se lee el renglón.
            'tipo' => $e->articulo?->tipo?->value,
            'clasificacion_abc' => $e->articulo?->clasificacion_abc?->value,
            // Null es legítimo: la carga inicial de los dos primeros almacenes
            // entró sin área, y clasificarla es trabajo pendiente.
            'area' => $e->articulo?->area?->descripcion,
            'se_controla_por_pieza' => (bool) $e->articulo?->se_controla_por_pieza,
            'stock_minimo' => $e->articulo?->stock_minimo === null
                ? null
                : (float) $e->articulo->stock_minimo,
            'cantidad' => (float) $e->cantidad,
            // Lo que anda afuera en resguardo sin haber salido del saldo. Sólo
            // suma en los activos por cantidad; las piezas lo dicen por estatus.
            'prestado' => (float) $e->prestado,
            // Lo que cualquiera puede llevarse sin pedirle permiso a nadie, y de
            // quién es el resto. `libre` no se guarda: sobra de repartir.
            'libre' => $e->libre(),
            'asignaciones' => $e->asignaciones
                ->filter(fn ($a): bool => (float) $a->cantidad > 0)
                ->map(fn ($a): array => [
                    'obra_id' => (int) $a->obra_id,
                    'obra' => $a->obra?->no,
                    'cantidad' => (float) $a->cantidad,
                ])
                ->values()
                ->all(),
            'costo_promedio' => (float) $e->costo_promedio,
            'valor' => (float) $e->valor,
            'ubicacion_id' => $e->ubicacion_id,
            'ubicacion' => $e->ubicacion?->ruta(),
            'ultimo_movimiento_at' => HoraLocal::texto($e->ultimo_movimiento_at),
            // Sólo en los renglones por pieza: el mismo saldo, pero sabiendo
            // en qué anda cada una. Cinco pulidoras con tres prestadas no
            // son cinco pulidoras que entregar.
            'piezas' => $piezas[$e->almacen_id.'|'.$e->articulo_id] ?? null,
        ]);

        return Inertia::render('admin/almacen/existencias/index', [
            ...$comunes,
            'existencias' => $existencias,
            'totales' => $totales,
            // Filtrar por lugar sólo tiene sentido dentro de un almacén: el
            // «Rack A-1» de AG no es el de FAK.
            'ubicaciones' => $almacenId === null ? [] : $this->ubicacionesDe($almacenId),
        ]);
    }

    /**
     * Lo filtrado, entero, a Excel.
     *
     * Misma pregunta que la pantalla —mismos filtros, misma visibilidad— sin
     * paginar. A diferencia de la tabla, no pide filtro: sin almacén elegido
     * salen todos los que el usuario ve, una hoja por almacén, y con uno
     * elegido sale sólo ese.
     */
    public function exportar(Request $request): BinaryFileResponse
    {
        $consulta = $this->consultaFiltrada($request, $this->almacenesVisibles($request));

        return Excel::download(new ExistenciasExport($consulta), 'existencias-'.now()->format('Ymd-Hi').'.xlsx');
    }

    /**
     * Lo que el usuario pidió ver, sin orden ni join: la misma base para los
     * renglones de la página y para el total del pie.
     *
     * @param  Collection<int, int>  $visibles
     * @return Builder<Existencia>
     */
    private function consultaFiltrada(Request $request, Collection $visibles): Builder
    {
        return Existencia::query()
            ->whereIn('almacen_id', $visibles)
            ->when(
                $request->integer('almacen_id') ?: null,
                fn (Builder $q, int $id) => $q->where('almacen_id', $id),
            )
            ->when(
                $request->integer('ubicacion_id') ?: null,
                fn (Builder $q, int $id) => $q->where('ubicacion_id', $id),
            )
            ->when(
                $request->string('obra_id')->value(),
                // `libre` es un filtro de primera clase: «qué material puedo
                // repartir» es la pregunta con la que se abre esta pantalla
                // cuando hay que asignar lo que ya estaba en bodega.
                fn (Builder $q, string $obra) => $obra === 'libre'
                    ? $q->whereDoesntHave('asignaciones', fn (Builder $a) => $a->vivas())
                    : $q->whereHas('asignaciones', fn (Builder $a) => $a->vivas()->where('obra_id', $obra)),
            )
            ->when(
                $request->integer('area_id') ?: null,
                // El área vive en el artículo, no en la existencia: el mismo
                // insumo es de la misma familia esté en la bodega que esté.
                fn (Builder $q, int $id) => $q->whereHas('articulo', fn (Builder $a) => $a->where('area_id', $id)),
            )
            ->when($request->boolean('sin_acomodar'), fn (Builder $q) => $q->sinAcomodar())
            ->when(
                $request->string('saldo')->value(),
                // «Sin existencia» es una pregunta de compras —qué se acabó—,
                // no un inventario recortado, y por eso comparte control con
                // «con existencia» en vez de ser otra casilla.
                fn (Builder $q, string $saldo) => $saldo === 'cero'
                    ? $q->sinSaldo()
                    : $q->conSaldo(),
            )
            ->when(
                $request->string('search')->trim()->value(),
                // `whereLike` sin distinguir mayusculas: en SQLite el LIKE ya
                // las ignora y en PostgreSQL no, asi que buscar "tornillo" no
                // encontraba "TORNILLO" y el buscador se portaba distinto en
                // desarrollo que en produccion. Laravel emite ILIKE donde toca.
                fn (Builder $q, string $s) => $q->whereHas(
                    'articulo',
                    fn (Builder $p) => $p->whereLike('codigo', "%{$s}%")
                        ->orWhereLike('descripcion', "%{$s}%")
                        ->orWhereLike('codigo_barras', "%{$s}%"),
                ),
            );
    }

    /**
     * Si ya hay una pregunta que contestar.
     *
     * «Con existencia» no cuenta: acota tan poco que traería casi el inventario
     * entero, que es justo lo que esta pantalla no hace de entrada. «Sin
     * existencia» sí, porque los renglones en cero son unos cuantos y son
     * justamente lo que se viene a ver.
     */
    private function hayFiltro(Request $request): bool
    {
        return $request->integer('almacen_id') !== 0
            || $request->integer('ubicacion_id') !== 0
            || $request->integer('area_id') !== 0
            || $request->string('obra_id')->isNotEmpty()
            || $request->string('search')->trim()->isNotEmpty()
            || $request->boolean('sin_acomodar')
            || $request->string('saldo')->value() === 'cero';
    }

    /**
     * Cuántas piezas hay de cada renglón serializado y en qué andan.
     *
     * Se calcula sólo para lo que está en pantalla y con una consulta agrupada:
     * pedirlo renglón por renglón sería una consulta por fila, y la pantalla que
     * más se usa a diario es justo ésta.
     *
     * @param  \Illuminate\Support\Collection<int, Existencia>  $enPantalla
     * @return array<string, array<string, int>>
     */
    private function desglosePiezas(\Illuminate\Support\Collection $enPantalla): array
    {
        $serializados = $enPantalla
            ->filter(fn (Existencia $e): bool => (bool) $e->articulo?->se_controla_por_pieza)
            ->pluck('articulo_id')
            ->filter()
            ->unique();

        if ($serializados->isEmpty()) {
            return [];
        }

        return Activo::query()
            ->vigentes()
            ->whereIn('articulo_id', $serializados)
            ->whereIn('almacen_id', $enPantalla->pluck('almacen_id')->unique())
            ->selectRaw('almacen_id, articulo_id, estatus, COUNT(*) as total')
            ->groupBy('almacen_id', 'articulo_id', 'estatus')
            ->get()
            ->groupBy(fn ($fila): string => $fila->almacen_id.'|'.$fila->articulo_id)
            ->map(fn ($filas): array => [
                'disponibles' => (int) ($filas->firstWhere('estatus', ActivoEstatus::Disponible)?->total ?? 0),
                'prestadas' => (int) ($filas->firstWhere('estatus', ActivoEstatus::Prestado)?->total ?? 0),
                'en_reparacion' => (int) ($filas->firstWhere('estatus', ActivoEstatus::EnReparacion)?->total ?? 0),
            ])
            ->all();
    }

    /**
     * @return Collection<int, int>
     */
    private function almacenesVisibles(Request $request): Collection
    {
        return Almacen::query()->visiblesPara($request->user())->pluck('id');
    }

    /**
     * @return Collection<int, Almacen>
     */
    private function almacenes(Request $request): Collection
    {
        return Almacen::query()
            ->visiblesPara($request->user())
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);
    }

    /**
     * @return list<array{id: int, ruta: string}>
     */
    private function ubicacionesDe(int $almacenId): array
    {
        return Ubicacion::query()
            ->where('almacen_id', $almacenId)
            ->activas()
            ->with('padre')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Ubicacion $u): array => ['id' => $u->id, 'ruta' => $u->ruta()])
            ->values()
            ->all();
    }
}
