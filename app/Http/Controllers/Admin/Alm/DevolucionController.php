<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\DevolucionStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Prestamo;
use App\Models\Alm\PrestamoDetalle;
use App\Models\Usuario;
use App\Services\Alm\RegistradorPrestamos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Devoluciones: lo que vuelve al almacén y deja de estar a nombre de alguien.
 *
 * No es un documento propio: es el cierre de renglones de resguardo. La
 * persona entrega lo que trae, venga del vale que venga, y cada resguardo que
 * se queda sin pendientes cierra solo. No mueve existencia: lo prestado
 * siempre fue del almacén.
 */
class DevolucionController extends Controller
{
    public function __construct(private readonly RegistradorPrestamos $registrador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $devueltos = PrestamoDetalle::query()
            ->whereNotNull('devuelto_en')
            ->whereHas('prestamo', fn ($q) => $q->whereIn('almacen_id', $visibles)
                ->when($request->integer('almacen_id') ?: null, fn ($w, int $id) => $w->where('almacen_id', $id)))
            ->with([
                'prestamo:id,folio,almacen_id,responsable_id',
                'prestamo.almacen:id,clave',
                'prestamo.responsable:id,name',
                'articulo:id,codigo,descripcion,unidad',
                'activo:id,no_serie',
                'receptor:id,name',
            ])
            ->orderByDesc('devuelto_en')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PrestamoDetalle $d): array => [
                'id' => $d->id,
                'fecha' => $d->devuelto_en?->toDateString(),
                'prestamo_id' => $d->prestamo_id,
                'folio' => $d->prestamo?->folio,
                'almacen' => $d->prestamo?->almacen?->clave,
                'devolvio' => $d->prestamo?->responsable?->name,
                'recibio' => $d->receptor?->name,
                'codigo' => $d->articulo?->codigo,
                'descripcion' => $d->articulo?->descripcion,
                'unidad' => $d->articulo?->unidad,
                'no_serie' => $d->activo?->no_serie,
                'cantidad_devuelta' => (float) $d->cantidad_devuelta,
                'cantidad' => (float) $d->cantidad,
                'condicion_salida' => $d->condicion_salida,
                'condicion_retorno' => $d->condicion_retorno,
            ]);

        $pendientes = PrestamoDetalle::query()
            ->whereHas('prestamo', fn ($q) => $q->whereIn('almacen_id', $visibles)->abiertos())
            ->whereColumn('cantidad_devuelta', '<', 'cantidad')
            ->count();

        return Inertia::render('admin/almacen/devoluciones/index', [
            'devoluciones' => $devueltos,
            'pendientes' => $pendientes,
            'filters' => $request->only(['almacen_id']),
            'almacenes' => $this->opcionesAlmacen($request),
        ]);
    }

    /**
     * Se elige a la persona y se palomea lo que entrega. Todo lo abierto de
     * los almacenes visibles viaja con la pantalla, agrupado por responsable:
     * son decenas de renglones, no miles, y así el cambio de persona no pide
     * nada al servidor.
     */
    public function create(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $abiertos = PrestamoDetalle::query()
            ->whereHas('prestamo', fn ($q) => $q->whereIn('almacen_id', $visibles)->abiertos())
            ->whereColumn('cantidad_devuelta', '<', 'cantidad')
            ->with([
                'prestamo:id,folio,almacen_id,responsable_id,fecha_salida,fecha_retorno_esperada,obra_id,grupo_trabajo_id',
                'prestamo.almacen:id,clave',
                'prestamo.responsable:id,name',
                'prestamo.obra:id,no',
                'prestamo.grupoTrabajo:id,descripcion',
                'articulo:id,codigo,descripcion,unidad',
                'activo:id,no_serie,marca,modelo',
            ])
            ->get()
            ->groupBy('prestamo.responsable_id')
            ->map(fn (Collection $renglones, string $responsableId): array => [
                'id' => $responsableId,
                'nombre' => $renglones->first()?->prestamo?->responsable?->name,
                'renglones' => $renglones
                    ->sortBy(fn (PrestamoDetalle $d) => $d->prestamo?->fecha_salida)
                    ->map(fn (PrestamoDetalle $d): array => [
                        'id' => $d->id,
                        'prestamo_id' => $d->prestamo_id,
                        'folio' => $d->prestamo?->folio,
                        'almacen' => $d->prestamo?->almacen?->clave,
                        'destino' => $d->prestamo?->destino(),
                        'fecha_salida' => $d->prestamo?->fecha_salida?->toDateString(),
                        'fecha_retorno_esperada' => $d->prestamo?->fecha_retorno_esperada?->toDateString(),
                        'vencido' => (bool) $d->prestamo?->estaVencido(),
                        'codigo' => $d->articulo?->codigo,
                        'descripcion' => $d->articulo?->descripcion,
                        'unidad' => $d->articulo?->unidad,
                        'por_pieza' => $d->esPorPieza(),
                        'no_serie' => $d->activo?->no_serie,
                        'pendiente' => $d->pendiente(),
                        'condicion_salida' => $d->condicion_salida,
                    ])
                    ->values()
                    ->all(),
            ])
            ->sortBy('nombre')
            ->values()
            ->all();

        return Inertia::render('admin/almacen/devoluciones/create', [
            'responsables' => $abiertos,
            'usuarios' => Usuario::query()->orderBy('name')->get(['id', 'name']),
            'prestamoId' => $request->integer('prestamo_id') ?: null,
        ]);
    }

    public function store(DevolucionStoreRequest $request): RedirectResponse
    {
        $visibles = $this->almacenesVisibles($request);
        $ids = array_map(fn ($r) => (int) $r['detalle_id'], $request->validated('renglones'));

        $ajenos = PrestamoDetalle::query()
            ->whereIn('id', $ids)
            ->whereHas('prestamo', fn ($q) => $q->whereNotIn('almacen_id', $visibles))
            ->exists();

        abort_if($ajenos, 403);

        $tocados = $this->registrador->devolver(
            renglones: $request->validated('renglones'),
            fecha: Carbon::parse($request->validated('fecha')),
            recibioId: $request->validated('recibido_por'),
            userId: $request->user()->getAuthIdentifier(),
        );

        $cerrados = $tocados->filter(fn (Prestamo $p): bool => ! $p->estaAbierto())->pluck('folio')->implode(', ');
        $mensaje = 'Devolución registrada.'.($cerrados !== '' ? " Cerró el resguardo {$cerrados}." : '');

        if ($tocados->count() === 1) {
            return to_route('admin.alm.prestamos.show', $tocados->first())->with('success', $mensaje);
        }

        return to_route('admin.alm.devoluciones.index')->with('success', $mensaje);
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
    private function opcionesAlmacen(Request $request): Collection
    {
        return Almacen::query()
            ->visiblesPara($request->user())
            ->activos()
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);
    }
}
