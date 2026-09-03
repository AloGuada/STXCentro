<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\AjusteStoreRequest;
use App\Models\Alm\Ajuste;
use App\Models\Alm\AjusteDetalle;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;
use App\Services\Alm\RegistradorAjuste;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El ajuste de existencias.
 *
 * Sin `edit`, `update` ni `destroy`: los documentos de almacén son inmutables.
 * Un ajuste equivocado se corrige con otro ajuste, y los dos quedan en el kardex
 * — que es exactamente lo que se quiere poder auditar.
 */
class AjusteController extends Controller
{
    public function __construct(private readonly RegistradorAjuste $registrador) {}

    public function index(Request $request): Response
    {
        $ajustes = Ajuste::query()
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->filtrados($request->only(['almacen_id', 'motivo', 'desde', 'hasta', 'search']))
            ->with(['almacen:id,clave,nombre', 'autorizador:id,name'])
            ->withCount('detalles')
            ->withSum('detalles as diferencia_neta', 'diferencia')
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Ajuste $a): array => [
                'id' => $a->id,
                'folio' => $a->folio,
                'fecha' => $a->fecha?->toDateString(),
                'almacen' => $a->almacen?->clave,
                'motivo' => $a->motivo->value,
                'motivo_etiqueta' => $a->motivo->etiqueta(),
                'renglones' => $a->detalles_count,
                'diferencia_neta' => (float) ($a->diferencia_neta ?? 0),
                'autorizo' => $a->autorizador?->name,
            ]);

        return Inertia::render('admin/almacen/ajustes/index', [
            'ajustes' => $ajustes,
            'filters' => $request->only(['almacen_id', 'motivo', 'desde', 'hasta', 'search']),
            ...$this->opciones($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/ajustes/create', [
            ...$this->opciones($request),
            'productos' => $this->productos(),
        ]);
    }

    public function store(AjusteStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        $ajuste = $this->registrador->registrar(
            cabecera: [
                'almacen_id' => $almacen->id,
                'motivo' => $request->string('motivo')->value(),
                'fecha' => $request->date('fecha'),
                'observaciones' => $request->input('observaciones'),
                'autorizado_por' => $request->user()->getAuthIdentifier(),
            ],
            renglones: $request->validated('detalles'),
            userId: $request->user()->getAuthIdentifier(),
        );

        return to_route('admin.alm.ajustes.show', $ajuste);
    }

    public function show(Request $request, Ajuste $ajuste): Response
    {
        abort_unless($ajuste->almacen->esVisiblePara($request->user()), 403);

        $ajuste->load([
            'almacen:id,clave,nombre,obra_id',
            'almacen.obra:id,no',
            'autorizador:id,name',
            'detalles.producto:id,codigo,descripcion,unidad',
        ]);

        return Inertia::render('admin/almacen/ajustes/show', [
            'ajuste' => [
                'id' => $ajuste->id,
                'folio' => $ajuste->folio,
                'fecha' => $ajuste->fecha?->toDateString(),
                'almacen' => $ajuste->almacen?->clave,
                'almacen_nombre' => $ajuste->almacen?->nombre,
                'obra' => $ajuste->almacen?->obra?->no,
                'motivo' => $ajuste->motivo->value,
                'motivo_etiqueta' => $ajuste->motivo->etiqueta(),
                'observaciones' => $ajuste->observaciones,
                'autorizo' => $ajuste->autorizador?->name,
                'creado_en' => $ajuste->created_at?->toDateTimeString(),
            ],
            'detalles' => $ajuste->detalles->map(fn (AjusteDetalle $d): array => [
                'id' => $d->id,
                'codigo' => $d->producto?->codigo,
                'descripcion' => $d->producto?->descripcion,
                'unidad' => $d->producto?->unidad,
                'cantidad_sistema' => (float) $d->cantidad_sistema,
                'cantidad_contada' => (float) $d->cantidad_contada,
                'diferencia' => (float) $d->diferencia,
                'costo_unitario' => $d->costo_unitario === null ? null : (float) $d->costo_unitario,
                'observaciones' => $d->observaciones,
            ])->all(),
        ]);
    }

    /**
     * Qué hay ahora mismo en ese almacén, para arrancar la hoja con lo que ya
     * está registrado en vez de tecleado a mano.
     *
     * Va aparte de los productos porque el saldo cambia por almacén, y la
     * pantalla no sabe cuál eligieron hasta que lo eligen.
     */
    public function existencias(Request $request, Almacen $almacen): JsonResponse
    {
        abort_unless($almacen->esVisiblePara($request->user()), 403);

        // Los articulos de ESTE almacen, no el catalogo entero. Es tambien la
        // lista con la que se captura: ofrecer lo que otra bodega guarda seria
        // ofrecer material que aqui no hay.
        //
        // Entran los que estan en cero: la carga inicial les abre existencia
        // igual, y contar cero tambien es informacion.
        return response()->json(
            Existencia::query()
                ->where('almacen_id', $almacen->id)
                ->whereNotNull('articulo_id')
                ->with('articulo:id,codigo,descripcion,unidad,requiere_verificacion')
                ->get()
                ->map(fn (Existencia $e): array => [
                    'id' => $e->articulo_id,
                    'articulo_id' => $e->articulo_id,
                    'codigo' => $e->articulo?->codigo,
                    'descripcion' => $e->articulo?->descripcion,
                    'unidad' => $e->articulo?->unidad,
                    'requiere_verificacion' => (bool) $e->articulo?->requiere_verificacion,
                    'cantidad' => (float) $e->cantidad,
                ])
                ->sortBy('descripcion')
                ->values()
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function almacenesVisibles(Request $request): Collection
    {
        return Almacen::query()->visiblesPara($request->user())->pluck('id');
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
            'motivos' => array_map(
                fn (AjusteMotivo $m): array => ['value' => $m->value, 'label' => $m->etiqueta()],
                AjusteMotivo::capturables(),
            ),
        ];
    }

    /**
     * Sólo lo que mueve existencia: un flete no se cuenta.
     *
     * @return Collection<int, Producto>
     */
    private function productos(): Collection
    {
        return Producto::query()
            ->deInventario()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion', 'unidad', 'requiere_verificacion']);
    }

    /**
     * El acta del conteo. Un ajuste mueve saldo sin que entre ni salga nada,
     * así que la hoja firmada es el respaldo de quién respondió por él.
     */
    public function pdf(Request $request, Ajuste $ajuste): HttpResponse
    {
        abort_unless($ajuste->almacen->esVisiblePara($request->user()), 403);

        $ajuste->load([
            'almacen:id,clave,nombre',
            'autorizador:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-ajuste', ['ajuste' => $ajuste])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("ajuste-{$ajuste->folio}.pdf");
    }
}
