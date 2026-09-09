<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\PrestamoStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Prestamo;
use App\Models\Alm\PrestamoDetalle;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Usuario;
use App\Services\Alm\RegistradorPrestamos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los resguardos: quién tiene qué activos, desde cuándo y hasta cuándo.
 *
 * Es la pregunta que un kardex por cantidad no puede contestar. Prestar no
 * mueve el saldo —lo prestado sigue siendo del almacén—, cambia la custodia;
 * por eso el documento vive aparte del kardex y no tiene edición ni borrado:
 * un resguardo se cierra devolviendo.
 */
class PrestamoController extends Controller
{
    public function __construct(private readonly RegistradorPrestamos $registrador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);
        $filtros = $request->only(['almacen_id', 'estatus', 'responsable_id', 'search']);

        $prestamos = Prestamo::query()
            ->whereIn('almacen_id', $visibles)
            ->filtrados($filtros)
            ->with(['almacen:id,clave', 'responsable:id,name', 'obra:id,no', 'grupoTrabajo:id,descripcion', 'detalles'])
            ->orderByRaw("CASE WHEN estatus = 'abierto' THEN 0 ELSE 1 END")
            ->orderBy('fecha_salida')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Prestamo $p): array => [
                'id' => $p->id,
                'folio' => $p->folio,
                'almacen' => $p->almacen?->clave,
                'responsable' => $p->responsable?->name,
                'destino' => $p->destino(),
                'fecha_salida' => $p->fecha_salida->toDateString(),
                'fecha_retorno_esperada' => $p->fecha_retorno_esperada?->toDateString(),
                'dias_fuera' => $p->diasFuera(),
                'vencido' => $p->estaVencido(),
                'renglones' => $p->detalles->count(),
                'pendiente' => $p->pendiente(),
                'unidades' => (float) $p->detalles->sum('cantidad'),
                'estatus' => $p->estatus->value,
                'estatus_etiqueta' => $p->estatus->etiqueta(),
            ]);

        $abiertos = Prestamo::query()->whereIn('almacen_id', $visibles)->abiertos();

        return Inertia::render('admin/almacen/prestamos/index', [
            'prestamos' => $prestamos,
            'filters' => $filtros,
            'resumen' => [
                'abiertos' => (clone $abiertos)->count(),
                'vencidos' => (clone $abiertos)->whereDate('fecha_retorno_esperada', '<', today())->count(),
            ],
            'almacenes' => $this->opcionesAlmacen($request),
            'responsables' => Usuario::query()
                ->whereIn('id', Prestamo::query()->whereIn('almacen_id', $visibles)->select('responsable_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/prestamos/create', [
            'almacenes' => $this->opcionesAlmacen($request),
            'usuarios' => Usuario::query()->orderBy('name')->get(['id', 'name']),
            'obras' => Obra::query()->orderBy('no')->get(['id', 'no', 'descripcion']),
            'gruposTrabajo' => GrupoTrabajo::query()->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    /**
     * Lo que ese almacén puede prestar hoy. Se pide al elegir el almacén: no
     * se sabe cuál hasta que lo eligen.
     */
    public function prestables(Request $request, Almacen $almacen): JsonResponse
    {
        abort_unless($almacen->esVisiblePara($request->user()), 403);

        return response()->json($this->registrador->prestables($almacen));
    }

    public function store(PrestamoStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        $prestamo = $this->registrador->prestar(
            almacen: $almacen,
            cabecera: $request->safe()->only([
                'responsable_id', 'obra_id', 'grupo_trabajo_id', 'fecha_salida',
                'fecha_retorno_esperada', 'autorizado_por', 'observaciones',
            ]),
            renglones: $request->validated('renglones'),
            userId: $request->user()->getAuthIdentifier(),
        );

        return to_route('admin.alm.prestamos.show', $prestamo)
            ->with('success', "Resguardo {$prestamo->folio} registrado.");
    }

    public function show(Request $request, Prestamo $prestamo): Response
    {
        abort_unless($prestamo->almacen->esVisiblePara($request->user()), 403);

        $prestamo->load([
            'almacen:id,clave,nombre',
            'responsable:id,name',
            'autorizador:id,name',
            'creador:id,name',
            'obra:id,no,descripcion',
            'grupoTrabajo:id,descripcion',
            'detalles.articulo:id,codigo,descripcion,unidad',
            'detalles.activo:id,no_serie,marca,modelo,estatus',
            'detalles.receptor:id,name',
        ]);

        return Inertia::render('admin/almacen/prestamos/show', [
            'prestamo' => [
                'id' => $prestamo->id,
                'folio' => $prestamo->folio,
                'almacen' => $prestamo->almacen?->clave,
                'almacen_nombre' => $prestamo->almacen?->nombre,
                'responsable' => $prestamo->responsable?->name,
                'destino' => $prestamo->destino(),
                'obra' => $prestamo->obra?->descripcion,
                'fecha_salida' => $prestamo->fecha_salida->toDateString(),
                'fecha_retorno_esperada' => $prestamo->fecha_retorno_esperada?->toDateString(),
                'dias_fuera' => $prestamo->diasFuera(),
                'vencido' => $prestamo->estaVencido(),
                'estatus' => $prestamo->estatus->value,
                'estatus_etiqueta' => $prestamo->estatus->etiqueta(),
                'autorizo' => $prestamo->autorizador?->name,
                'entrego' => $prestamo->creador?->name,
                'cerrado_en' => $prestamo->cerrado_en?->toDateTimeString(),
                'observaciones' => $prestamo->observaciones,
                'pendiente' => $prestamo->pendiente(),
            ],
            'detalles' => $prestamo->detalles->map(fn (PrestamoDetalle $d): array => [
                'id' => $d->id,
                'codigo' => $d->articulo?->codigo,
                'descripcion' => $d->articulo?->descripcion,
                'unidad' => $d->articulo?->unidad,
                'por_pieza' => $d->esPorPieza(),
                'no_serie' => $d->activo?->no_serie,
                'marca_modelo' => trim(implode(' ', array_filter([$d->activo?->marca, $d->activo?->modelo]))),
                'cantidad' => (float) $d->cantidad,
                'cantidad_devuelta' => (float) $d->cantidad_devuelta,
                'pendiente' => $d->pendiente(),
                'condicion_salida' => $d->condicion_salida,
                'condicion_retorno' => $d->condicion_retorno,
                'devuelto_en' => $d->devuelto_en?->toDateString(),
                'recibio' => $d->receptor?->name,
                'observaciones' => $d->observaciones,
            ])->values()->all(),
            'usuarios' => Usuario::query()->orderBy('name')->get(['id', 'name']),
            'puede_devolver' => $request->user()->can('alm.devoluciones.crear'),
        ]);
    }

    /**
     * El resguardo para firmar: quien se lo lleva responde con su firma por
     * cada renglón.
     */
    public function pdf(Request $request, Prestamo $prestamo): HttpResponse
    {
        abort_unless($prestamo->almacen->esVisiblePara($request->user()), 403);

        $prestamo->load([
            'almacen:id,clave,nombre',
            'responsable:id,name',
            'autorizador:id,name',
            'creador:id,name',
            'obra:id,no',
            'grupoTrabajo:id,descripcion',
            'detalles.articulo:id,codigo,descripcion,unidad',
            'detalles.activo:id,no_serie,marca,modelo',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-prestamo', ['prestamo' => $prestamo])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("resguardo-{$prestamo->folio}.pdf");
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
