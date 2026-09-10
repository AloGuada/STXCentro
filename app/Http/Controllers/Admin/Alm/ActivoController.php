<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\ProductoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\ActivoStoreRequest;
use App\Http\Requests\Admin\Alm\ActivoUpdateRequest;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Services\Alm\RegistradorPiezas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El padrón de activos.
 *
 * Los que llevan serie son una fila por pieza: cada una suma 1 a la existencia
 * de su artículo, y quien mantiene ese uno a uno es `RegistradorPiezas` — por
 * eso el alta y la baja pasan por él y no por el modelo directo. Los que el
 * catálogo marca como activo **sin** control por pieza —extensiones, arneses—
 * son un solo renglón por cantidad: su registro es la existencia misma, y aquí
 * se enseñan al lado de las piezas para que Activos sea la lista completa de lo
 * que sale y regresa.
 *
 * Sin `show`: la pieza se corrige desde el modal de lápiz de su renglón, porque
 * son seis campos y una ficha propia sólo agregaría un clic.
 */
class ActivoController extends Controller
{
    public function __construct(private readonly RegistradorPiezas $registrador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $activos = Activo::query()
            ->whereIn('almacen_id', $visibles)
            ->filtrados($request->only(['almacen_id', 'articulo_id', 'estatus', 'search']))
            ->with([
                'producto:id,codigo,descripcion,unidad',
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'ubicacion.padre',
            ])
            ->orderBy('articulo_id')
            ->orderBy('no_serie')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activo $a): array => [
                'id' => $a->id,
                'articulo_id' => $a->articulo_id,
                'codigo' => $a->articulo?->codigo,
                'descripcion' => $a->articulo?->descripcion,
                'no_serie' => $a->no_serie,
                'codigo_barras' => $a->codigo_barras,
                'marca' => $a->marca,
                'modelo' => $a->modelo,
                'id_mantenimiento' => $a->id_mantenimiento,
                'almacen_id' => $a->almacen_id,
                'almacen' => $a->almacen?->clave,
                'obra' => $a->almacen?->obra?->no,
                'ubicacion_id' => $a->ubicacion_id,
                'ubicacion' => $a->ubicacion?->ruta(),
                'costo' => (float) $a->costo,
                'estatus' => $a->estatus->value,
                'estatus_etiqueta' => $a->estatus->etiqueta(),
                'condicion' => $a->condicion,
            ]);

        return Inertia::render('admin/almacen/activos/index', [
            'activos' => $activos,
            'porCantidad' => $this->porCantidad($request, $visibles),
            'filters' => $request->only(['almacen_id', 'articulo_id', 'estatus', 'search']),
            'ubicacionesPorAlmacen' => $this->ubicacionesPorAlmacen($visibles),
            ...$this->opciones($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/activos/create', [
            ...$this->opciones($request),
            'ubicacionesPorAlmacen' => $this->ubicacionesPorAlmacen($this->almacenesVisibles($request)),
        ]);
    }

    /**
     * El alta decide su forma por el catálogo: con serie, una pieza por renglón;
     * sin serie, un solo asiento por la cantidad que entra.
     */
    public function store(ActivoStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        $articulo = Articulo::findOrFail($request->integer('articulo_id'));

        if ($request->esPorCantidad()) {
            $this->registrador->altaPorCantidad(
                articulo: $articulo,
                almacen: $almacen,
                cantidad: (float) $request->validated('cantidad'),
                costo: $request->filled('costo') ? (float) $request->validated('costo') : null,
                ubicacionId: $request->integer('ubicacion_id') ?: null,
                userId: $request->user()->getAuthIdentifier(),
                observaciones: $request->validated('observaciones'),
            );
        } else {
            $this->registrador->alta(
                articulo: $articulo,
                almacen: $almacen,
                piezas: $request->validated('piezas'),
                ubicacionId: $request->integer('ubicacion_id') ?: null,
                userId: $request->user()->getAuthIdentifier(),
            );
        }

        return to_route('admin.alm.activos.index', ['almacen_id' => $almacen->id]);
    }

    /**
     * Retira N unidades de un activo por cantidad. Como la baja de una pieza,
     * descarga existencia y deja su asiento; sin serie, lo que se dice es
     * cuántas y por qué.
     */
    public function bajaPorCantidad(Request $request, Existencia $existencia): RedirectResponse
    {
        abort_unless($existencia->almacen->esVisiblePara($request->user()), 403);
        abort_unless($existencia->articulo?->esActivoPorCantidad(), 404);

        $validado = $request->validate(
            [
                'cantidad' => ['required', 'numeric', 'gt:0', 'lte:'.(float) $existencia->cantidad],
                'motivo' => ['required', 'string', 'max:255'],
            ],
            [
                'cantidad.required' => 'Di cuántas se retiran.',
                'cantidad.lte' => 'No se pueden retirar más de las que hay.',
                'motivo.required' => 'Escribe por qué se retiran: queda en el kardex.',
            ],
        );

        $this->registrador->bajaPorCantidad(
            $existencia,
            (float) $validado['cantidad'],
            $validado['motivo'],
            $request->user()->getAuthIdentifier(),
        );

        return back();
    }

    public function update(ActivoUpdateRequest $request, Activo $activo): RedirectResponse
    {
        abort_unless($activo->almacen->esVisiblePara($request->user()), 403);

        $activo->update($request->validated());

        return back();
    }

    /**
     * Retira la pieza. Va aparte de la edición porque descarga existencia: es un
     * movimiento del kardex, no una corrección de datos.
     */
    public function baja(Request $request, Activo $activo): RedirectResponse
    {
        abort_unless($activo->almacen->esVisiblePara($request->user()), 403);

        $validado = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Escribe por qué se retira la pieza: queda en el kardex.'],
        );

        $this->registrador->baja($activo, $validado['motivo'], $request->user()->getAuthIdentifier());

        return back();
    }

    /**
     * Los activos sin serie: un renglón por almacén y artículo, que es su
     * existencia. Responden a los mismos filtros que las piezas menos el
     * estado, que sin serie no existe todavía.
     *
     * @param  Collection<int, int>  $visibles
     * @return list<array<string, mixed>>
     */
    private function porCantidad(Request $request, Collection $visibles): array
    {
        $busqueda = $request->string('search')->trim()->value();

        return Existencia::query()
            ->whereIn('alm_existencias.almacen_id', $visibles)
            ->whereHas('articulo', fn ($q) => $q->activosPorCantidad()->where('activo', true))
            ->when($request->integer('almacen_id') ?: null, fn ($q, int $id) => $q->where('alm_existencias.almacen_id', $id))
            ->when($request->integer('articulo_id') ?: null, fn ($q, int $id) => $q->where('alm_existencias.articulo_id', $id))
            ->when($busqueda !== '', fn ($q) => $q->whereHas('articulo', fn ($a) => $a
                ->where(fn ($w) => $w
                    ->whereLike('codigo', "%{$busqueda}%")
                    ->orWhereLike('descripcion', "%{$busqueda}%"))))
            ->with([
                'articulo:id,codigo,descripcion,unidad',
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'ubicacion.padre',
            ])
            ->join('alm_articulos', 'alm_articulos.id', '=', 'alm_existencias.articulo_id')
            ->orderBy('alm_articulos.descripcion')
            ->orderBy('alm_existencias.almacen_id')
            ->select('alm_existencias.*')
            ->limit(300)
            ->get()
            ->map(fn (Existencia $e): array => [
                'id' => $e->id,
                'articulo_id' => $e->articulo_id,
                'codigo' => $e->articulo?->codigo,
                'descripcion' => $e->articulo?->descripcion,
                'unidad' => $e->articulo?->unidad,
                'almacen_id' => $e->almacen_id,
                'almacen' => $e->almacen?->clave,
                'obra' => $e->almacen?->obra?->no,
                'ubicacion' => $e->ubicacion?->ruta(),
                'cantidad' => (float) $e->cantidad,
                'prestado' => (float) $e->prestado,
                'disponible' => $e->disponibleParaPrestar(),
                'costo_promedio' => (float) $e->costo_promedio,
                'valor' => (float) $e->valor,
            ])
            ->values()
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
     * Los lugares activos de cada almacén, agrupados: el alta y el modal de
     * corrección necesitan ofrecer sólo los del almacén de la pieza.
     *
     * @param  Collection<int, int>  $almacenes
     * @return array<int, list<array{id: int, ruta: string}>>
     */
    private function ubicacionesPorAlmacen(Collection $almacenes): array
    {
        return Ubicacion::query()
            ->whereIn('almacen_id', $almacenes)
            ->activas()
            ->with('padre')
            ->orderBy('codigo')
            ->get()
            ->groupBy('almacen_id')
            ->map(fn ($grupo) => $grupo
                ->map(fn (Ubicacion $u): array => ['id' => $u->id, 'ruta' => $u->ruta()])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(Request $request): array
    {
        return [
            'almacenes' => Almacen::query()
                ->visiblesPara($request->user())
                ->activos()
                ->with('obra:id,no')
                ->orderBy('obra_id')
                ->orderBy('clave')
                ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']),
            // Todos los activos, con o sin serie: el alta decide su forma por
            // esa bandera. Un insumo no entra: darle número de serie a un
            // tornillo no significa nada, y tampoco se «da de alta» aquí.
            'articulos' => Articulo::query()
                ->where('tipo', ProductoTipo::Activo)
                ->where('activo', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo', 'descripcion', 'unidad', 'requiere_verificacion', 'se_controla_por_pieza']),
            'estatuses' => array_map(
                fn (ActivoEstatus $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                ActivoEstatus::cases(),
            ),
        ];
    }
}
