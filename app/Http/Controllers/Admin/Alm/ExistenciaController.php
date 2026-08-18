<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Http\Controllers\Controller;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Services\Alm\SaldoEnTransito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla de diario: qué hay y cuánto en cada almacén.
 *
 * Sin filtro de almacén es el consolidado de la empresa; con filtro, el
 * inventario de esa bodega. Es sólo lectura — lo único que se corrige desde
 * aquí es dónde está acomodado el material, y eso vive en `UbicacionController`
 * porque es una decisión sobre el lugar, no sobre el saldo.
 */
class ExistenciaController extends Controller
{
    public function __construct(private readonly SaldoEnTransito $transito) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);
        $almacenId = $request->integer('almacen_id') ?: null;

        $existencias = Existencia::query()
            ->whereIn('almacen_id', $visibles)
            ->with([
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'producto:id,codigo,descripcion,unidad,stock_minimo,se_controla_por_pieza,clasificacion_abc',
                'ubicacion.padre',
            ])
            ->when($almacenId, fn (Builder $q, int $id) => $q->where('almacen_id', $id))
            ->when(
                $request->integer('ubicacion_id') ?: null,
                fn (Builder $q, int $id) => $q->where('ubicacion_id', $id),
            )
            ->when($request->boolean('sin_acomodar'), fn (Builder $q) => $q->sinAcomodar())
            ->when($request->boolean('solo_con_saldo'), fn (Builder $q) => $q->conSaldo())
            ->when(
                $request->string('search')->trim()->value(),
                fn (Builder $q, string $s) => $q->whereHas(
                    'producto',
                    fn (Builder $p) => $p->where('codigo', 'like', "%{$s}%")
                        ->orWhere('descripcion', 'like', "%{$s}%")
                        ->orWhere('codigo_barras', 'like', "%{$s}%"),
                ),
            )
            ->join('costos_productos', 'costos_productos.id', '=', 'alm_existencias.producto_id')
            ->orderBy('costos_productos.descripcion')
            ->select('alm_existencias.*')
            ->paginate(50)
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
            'producto_id' => $e->producto_id,
            'codigo' => $e->producto?->codigo,
            'descripcion' => $e->producto?->descripcion,
            'unidad' => $e->producto?->unidad,
            'clasificacion_abc' => $e->producto?->clasificacion_abc?->value,
            'se_controla_por_pieza' => (bool) $e->producto?->se_controla_por_pieza,
            'stock_minimo' => $e->producto?->stock_minimo === null
                ? null
                : (float) $e->producto->stock_minimo,
            'cantidad' => (float) $e->cantidad,
            'costo_promedio' => (float) $e->costo_promedio,
            'valor' => (float) $e->valor,
            'ubicacion_id' => $e->ubicacion_id,
            'ubicacion' => $e->ubicacion?->ruta(),
            'ultimo_movimiento_at' => $e->ultimo_movimiento_at?->toDateTimeString(),
            // Sólo en los renglones por pieza: el mismo saldo, pero sabiendo
            // en qué anda cada una. Cinco pulidoras con tres prestadas no
            // son cinco pulidoras que entregar.
            'piezas' => $piezas[$e->almacen_id.'|'.$e->producto_id] ?? null,
        ]);

        return Inertia::render('admin/almacen/existencias/index', [
            'existencias' => $existencias,
            'filters' => $request->only(['almacen_id', 'ubicacion_id', 'search', 'sin_acomodar', 'solo_con_saldo']),
            'resumen' => $this->resumen($request, $visibles, $almacenId),
            'almacenes' => $this->almacenes($request),
            // Filtrar por lugar sólo tiene sentido dentro de un almacén: el
            // «Rack A-1» de AG no es el de FAK.
            'ubicaciones' => $almacenId === null ? [] : $this->ubicacionesDe($almacenId),
        ]);
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
            ->filter(fn (Existencia $e): bool => (bool) $e->producto?->se_controla_por_pieza)
            ->pluck('producto_id')
            ->unique();

        if ($serializados->isEmpty()) {
            return [];
        }

        return Activo::query()
            ->vigentes()
            ->whereIn('producto_id', $serializados)
            ->whereIn('almacen_id', $enPantalla->pluck('almacen_id')->unique())
            ->selectRaw('almacen_id, producto_id, estatus, COUNT(*) as total')
            ->groupBy('almacen_id', 'producto_id', 'estatus')
            ->get()
            ->groupBy(fn ($fila): string => $fila->almacen_id.'|'.$fila->producto_id)
            ->map(fn ($filas): array => [
                'disponibles' => (int) ($filas->firstWhere('estatus', ActivoEstatus::Disponible)?->total ?? 0),
                'prestadas' => (int) ($filas->firstWhere('estatus', ActivoEstatus::Prestado)?->total ?? 0),
                'en_reparacion' => (int) ($filas->firstWhere('estatus', ActivoEstatus::EnReparacion)?->total ?? 0),
            ])
            ->all();
    }

    /**
     * Lo que vale la bodega y lo que hay que ir a atender. Se calcula sobre el
     * almacén elegido —o sobre todo lo visible—, no sobre la página: un total
     * que cambia al pasar de página no significa nada.
     *
     * @param  Collection<int, int>  $visibles
     * @return array<string, mixed>
     */
    private function resumen(Request $request, Collection $visibles, ?int $almacenId): array
    {
        $base = fn (): Builder => Existencia::query()
            ->whereIn('almacen_id', $visibles)
            ->when($almacenId, fn (Builder $q, int $id) => $q->where('almacen_id', $id));

        $fila = $base()
            ->selectRaw('COUNT(*) as renglones')
            ->selectRaw('COALESCE(SUM(valor), 0) as valor')
            ->first();

        return [
            'renglones' => (int) ($fila->renglones ?? 0),
            'valor' => (float) ($fila->valor ?? 0),
            'con_saldo' => $base()->conSaldo()->count(),
            // Material que nadie acomodó: es la lista de trabajo del almacenista,
            // por eso va a la vista en vez de esconderse tras un filtro.
            'sin_acomodar' => $base()->conSaldo()->sinAcomodar()->count(),
            // No debería pasar nunca; justo por eso hay que poder verlo.
            'en_negativo' => $base()->enNegativo()->count(),
        ];
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
