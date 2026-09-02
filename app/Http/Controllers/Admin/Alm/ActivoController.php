<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\ActivoStoreRequest;
use App\Http\Requests\Admin\Alm\ActivoUpdateRequest;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Services\Alm\RegistradorPiezas;
use App\Services\Alm\ResolvedorArticulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El padrón de piezas: una fila por número de serie.
 *
 * No es un inventario aparte del kardex. Cada pieza suma 1 a la existencia de su
 * artículo, y quien mantiene ese uno a uno es `RegistradorPiezas` — por eso el
 * alta y la baja pasan por él y no por el modelo directo.
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
            ->filtrados($request->only(['almacen_id', 'producto_id', 'estatus', 'search']))
            ->with([
                'producto:id,codigo,descripcion,unidad',
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'ubicacion.padre',
            ])
            ->orderBy('producto_id')
            ->orderBy('no_serie')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activo $a): array => [
                'id' => $a->id,
                'producto_id' => $a->producto_id,
                'codigo' => $a->producto?->codigo,
                'descripcion' => $a->producto?->descripcion,
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
            'filters' => $request->only(['almacen_id', 'producto_id', 'estatus', 'search']),
            'resumen' => $this->resumen($request, $visibles),
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

    public function store(ActivoStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        // El formulario todavia manda el producto; el articulo se resuelve
        // aqui y se crea si es la primera vez que ese producto pisa la bodega.
        $articuloId = app(ResolvedorArticulo::class)->paraProducto($request->integer('producto_id'));

        $this->registrador->alta(
            articulo: Articulo::findOrFail($articuloId),
            almacen: $almacen,
            piezas: $request->validated('piezas'),
            ubicacionId: $request->integer('ubicacion_id') ?: null,
            userId: $request->user()->getAuthIdentifier(),
        );

        return to_route('admin.alm.activos.index', ['almacen_id' => $almacen->id]);
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
     * Cuántas hay y cuántas se pueden prometer hoy. Prestada y en reparación
     * siguen siendo de la empresa; lo que no son es *disponibles*.
     *
     * @param  Collection<int, int>  $visibles
     * @return array<string, int>
     */
    private function resumen(Request $request, Collection $visibles): array
    {
        $base = fn () => Activo::query()
            ->whereIn('almacen_id', $visibles)
            ->when($request->integer('almacen_id') ?: null, fn ($q, int $id) => $q->where('almacen_id', $id));

        return [
            'vigentes' => $base()->vigentes()->count(),
            'disponibles' => $base()->where('estatus', ActivoEstatus::Disponible)->count(),
            'prestadas' => $base()->where('estatus', ActivoEstatus::Prestado)->count(),
            'en_reparacion' => $base()->where('estatus', ActivoEstatus::EnReparacion)->count(),
            'baja' => $base()->where('estatus', ActivoEstatus::Baja)->count(),
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
            // Sólo lo marcado «por pieza»: darle número de serie a un tornillo
            // no significa nada.
            'articulos' => Producto::query()
                ->porPieza()
                ->where('activo', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo', 'descripcion', 'unidad', 'requiere_verificacion']),
            'estatuses' => array_map(
                fn (ActivoEstatus $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                ActivoEstatus::cases(),
            ),
        ];
    }
}
