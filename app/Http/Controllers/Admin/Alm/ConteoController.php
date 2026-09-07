<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ConteoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\ConteoProgramaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Conteo;
use App\Models\Alm\ConteoDetalle;
use App\Models\Alm\ConteoPrograma;
use App\Services\Alm\GeneradorProgramaConteo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Inventarios cíclicos: contar un pedazo del almacén cada día en vez de parar
 * todo un fin de semana.
 *
 * Esta pantalla **programa y reparte**: el modal genera las hojas y el show
 * enseña e imprime la lista que toca ese día. La captura de lo contado y el
 * cierre que genera el ajuste son la siguiente rebanada; los renglones ya
 * tienen dónde guardarlo.
 */
class ConteoController extends Controller
{
    public function __construct(private readonly GeneradorProgramaConteo $generador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $conteos = Conteo::query()
            ->whereIn('almacen_id', $visibles)
            ->filtrados($request->only(['almacen_id', 'estatus', 'programa_id', 'search']))
            ->with(['almacen:id,clave,nombre', 'responsable:id,name', 'ajuste:id,folio'])
            ->withCount('detalles')
            ->withCount(['detalles as contados_count' => fn ($q) => $q->whereNotNull('cantidad_contada')])
            ->orderBy('fecha_programada')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Conteo $c): array => [
                'id' => $c->id,
                'folio' => $c->folio,
                'origen' => $c->origen->value,
                'origen_etiqueta' => $c->origen->etiqueta(),
                'almacen' => $c->almacen?->clave,
                'programa_id' => $c->programa_id,
                'fecha_programada' => $c->fecha_programada->toDateString(),
                'responsable' => $c->responsable?->name,
                'estatus' => $c->estatus->value,
                'estatus_etiqueta' => $c->estatus->etiqueta(),
                'vencido' => $c->vencido(),
                'renglones' => $c->detalles_count,
                'contados' => $c->contados_count,
                'ajuste_folio' => $c->ajuste?->folio,
            ]);

        $programas = ConteoPrograma::query()
            ->whereIn('almacen_id', $visibles)
            ->with('almacen:id,clave,nombre')
            ->withCount('conteos')
            ->withCount(['conteos as cerrados_count' => fn ($q) => $q->where('estatus', ConteoEstatus::Cerrado->value)])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ConteoPrograma $p): array => [
                'id' => $p->id,
                'almacen' => $p->almacen?->clave,
                'fecha_inicio' => $p->fecha_inicio->toDateString(),
                'dias_semana' => $p->dias_semana,
                'duracion_dias' => $p->duracion_dias,
                'articulos_por_dia' => $p->articulos_por_dia,
                'articulos_programados' => $p->articulos_programados,
                'articulos_sin_programar' => $p->articulos_sin_programar,
                'hojas' => $p->conteos_count,
                'cerradas' => $p->cerrados_count,
            ]);

        $abiertos = Conteo::query()->whereIn('almacen_id', $visibles)->abiertos();

        return Inertia::render('admin/almacen/conteos/index', [
            'conteos' => $conteos,
            'programas' => $programas,
            'filters' => $request->only(['almacen_id', 'estatus', 'programa_id', 'search']),
            'resumen' => [
                'abiertos' => (clone $abiertos)->count(),
                'vencidos' => (clone $abiertos)->whereDate('fecha_programada', '<', today())->count(),
                'programados_hoy' => (clone $abiertos)->whereDate('fecha_programada', today())->count(),
            ],
            'almacenes' => $this->almacenes($request),
            'estatus' => array_map(
                fn (ConteoEstatus $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                ConteoEstatus::cases(),
            ),
        ]);
    }

    /**
     * Genera el programa: un lote de hojas, una por día de conteo. Las
     * decisiones de reparto viven en el servicio; aquí sólo se valida que el
     * almacén sea de quien lo pide y se traduce el resultado a un mensaje.
     */
    public function storePrograma(ConteoProgramaStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::query()->findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        try {
            $programa = $this->generador->generar(
                almacen: $almacen,
                fechaInicio: CarbonImmutable::parse($request->string('fecha_inicio')->value()),
                diasSemana: array_map('intval', $request->input('dias_semana', [])),
                duracionDias: $request->integer('duracion_dias'),
                articulosPorDia: $request->integer('articulos_por_dia'),
                userId: $request->user()?->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['duracion_dias' => $e->getMessage()])->withInput();
        }

        $hojas = $programa->conteos->count();
        $primera = $programa->conteos->first()?->fecha_programada->format('d/m/Y');
        $ultima = $programa->conteos->last()?->fecha_programada->format('d/m/Y');
        $mensaje = "Se generaron {$hojas} hojas para {$almacen->clave}, del {$primera} al {$ultima}, con {$programa->articulos_programados} artículos.";

        if ($programa->articulos_sin_programar > 0) {
            $mensaje .= " Quedaron {$programa->articulos_sin_programar} artículos sin programar: no cupieron en los días elegidos.";
        }

        return to_route('admin.alm.conteos.index', ['programa_id' => $programa->id])->with('success', $mensaje);
    }

    public function show(Request $request, Conteo $conteo): Response
    {
        abort_unless($conteo->almacen->esVisiblePara($request->user()), 403);

        $conteo->load([
            'almacen:id,clave,nombre',
            'responsable:id,name',
            'ajuste:id,folio',
            'programa:id,fecha_inicio,duracion_dias,articulos_por_dia',
            'detalles.articulo:id,codigo,descripcion,unidad,clasificacion_abc',
            'detalles.existencia:id,ubicacion_id',
            'detalles.existencia.ubicacion',
        ]);

        return Inertia::render('admin/almacen/conteos/show', [
            'conteo' => [
                'id' => $conteo->id,
                'folio' => $conteo->folio,
                'origen' => $conteo->origen->value,
                'origen_etiqueta' => $conteo->origen->etiqueta(),
                'almacen' => $conteo->almacen?->clave,
                'almacen_nombre' => $conteo->almacen?->nombre,
                'programa_id' => $conteo->programa_id,
                'fecha_programada' => $conteo->fecha_programada->toDateString(),
                'fecha_cierre' => $conteo->fecha_cierre?->toDateString(),
                'responsable' => $conteo->responsable?->name,
                'estatus' => $conteo->estatus->value,
                'estatus_etiqueta' => $conteo->estatus->etiqueta(),
                'vencido' => $conteo->vencido(),
                'ajuste_folio' => $conteo->ajuste?->folio,
                'observaciones' => $conteo->observaciones,
                'renglones' => $conteo->detalles->map(fn (ConteoDetalle $d): array => [
                    'id' => $d->id,
                    'orden' => $d->orden,
                    'articulo_id' => $d->articulo_id,
                    'codigo' => $d->articulo?->codigo,
                    'descripcion' => $d->articulo?->descripcion,
                    'unidad' => $d->articulo?->unidad,
                    'clasificacion' => $d->articulo?->clasificacion_abc?->value,
                    'ubicacion' => $d->existencia?->ubicacion?->ruta(),
                    'cantidad_sistema' => $d->cantidad_sistema === null ? null : (float) $d->cantidad_sistema,
                    'cantidad_contada' => $d->cantidad_contada === null ? null : (float) $d->cantidad_contada,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * La hoja para caminar el almacén. Va **sin** el saldo del sistema a
     * propósito: un número a la vista es una respuesta sugerida, y el conteo
     * sirve como control justamente porque quien cuenta no sabe cuánto debería
     * haber.
     */
    public function pdf(Request $request, Conteo $conteo): HttpResponse
    {
        abort_unless($conteo->almacen->esVisiblePara($request->user()), 403);

        $conteo->load([
            'almacen:id,clave,nombre',
            'responsable:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
            'detalles.existencia:id,ubicacion_id',
            'detalles.existencia.ubicacion',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-conteo', ['conteo' => $conteo])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("conteo-{$conteo->folio}.pdf");
    }

    /**
     * @return Collection<int, int>
     */
    private function almacenesVisibles(Request $request): Collection
    {
        return Almacen::query()->visiblesPara($request->user())->pluck('id');
    }

    /**
     * Los almacenes con cuántos artículos tiene cada uno para contar: es lo
     * que el modal usa para decir cuántas hojas van a salir antes de generar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function almacenes(Request $request): Collection
    {
        return Almacen::query()
            ->visiblesPara($request->user())
            ->activos()
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id'])
            ->map(fn (Almacen $a): array => [
                'id' => $a->id,
                'clave' => $a->clave,
                'nombre' => $a->nombre,
                'obra' => $a->obra?->no,
                'articulos' => GeneradorProgramaConteo::articulosContables($a),
            ]);
    }
}
