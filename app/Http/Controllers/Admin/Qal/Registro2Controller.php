<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Exports\Qal\Registro2InspeccionesExport;
use App\Exports\Qal\Registro2SublotesExport;
use App\Http\Controllers\Controller;
use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\Obra;
use App\Models\Qal\Sublote;
use App\Services\Qal\FichaDeRegistro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Registro 2: la misma base en crudo que Registros, en paralelo y sin tocarla.
 *
 * Lee las mismas tablas —`qal_inspecciones` para piezas, `qal_sublotes` para
 * accesorios— y conserva sus criterios: registro ≠ pieza y de cada sublote
 * manda su última inspección. Lo que cambia es lo que pedía el backlog: el
 * periodo es un rango y no un solo día, una obra que no existe se descarta, y
 * el catálogo de inspectores trae sólo a quienes tienen registros, activos o
 * no, para que el historial de quien ya se fue se siga pudiendo filtrar.
 */
class Registro2Controller extends Controller
{
    private const POR_PAGINA = 50;

    private const ORDEN_PIEZAS = ['fecha', 'fase', 'marca', 'consecutivo', 'numero_inspeccion', 'modulo', 'estatus'];

    private const ORDEN_SUBLOTES = ['fecha', 'fase', 'unidades', 'muestra', 'rechazadas', 'veredicto', 'numero_inspeccion'];

    public function index(Request $request, FichaDeRegistro $fichas): Response
    {
        $filtros = $this->filtros($request);
        $esPiezas = $filtros['que'] === 'pza';

        return Inertia::render('admin/calidad/registros2/index', [
            'filtros' => $filtros,
            'obras' => fn () => Obra::opcionesDeSelector(soloActivas: false),
            'inspectores' => fn () => $this->inspectores(),
            'registros' => fn () => $esPiezas ? $this->piezas($filtros) : null,
            'sublotes' => fn () => $esPiezas ? null : $this->sublotes($filtros),
            'conteo' => fn () => $this->conteo($filtros),
            'ficha' => fn () => $request->filled('ficha')
                ? $fichas->deInspeccion(Inspeccion::query()->findOrFail($request->integer('ficha')))
                : null,
            'fichaSublote' => fn () => $request->filled('sublote')
                ? $fichas->deSublote(Sublote::query()->findOrFail($request->integer('sublote')))
                : null,
        ]);
    }

    /**
     * Exporta lo mismo que filtra la pantalla, periodo incluido. Cuelga del
     * mismo permiso que la exportación de Registros.
     */
    public function exportar(Request $request): BinaryFileResponse
    {
        $filtros = $this->filtros($request);
        $fecha = now()->format('Ymd');

        return $filtros['que'] === 'acc'
            ? Excel::download(new Registro2SublotesExport($filtros), "calidad-sublotes-{$fecha}.xlsx")
            : Excel::download(new Registro2InspeccionesExport($filtros), "calidad-inspecciones-{$fecha}.xlsx");
    }

    /**
     * Lo que no se reconoce se descarta en vez de filtrar por ello, igual que en
     * Registros. Un periodo al revés se endereza: quien pide del 15 al 1 quiere
     * esa quincena, no una tabla vacía.
     *
     * @return array{que: string, obra: int|null, fase: string|null, inspector: int|null, estatus: string|null, desde: string|null, hasta: string|null, buscar: string|null, sort_by: string, sort_dir: string}
     */
    private function filtros(Request $request): array
    {
        $que = $request->query('que') === 'acc' ? 'acc' : 'pza';
        $orden = $que === 'acc' ? self::ORDEN_SUBLOTES : self::ORDEN_PIEZAS;
        $obra = $request->integer('obra') ?: null;
        $desde = $this->fecha($request->query('desde'));
        $hasta = $this->fecha($request->query('hasta'));
        $buscar = trim((string) $request->query('buscar', ''));

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'que' => $que,
            'obra' => $obra !== null && ObraDelPortal::query()->whereKey($obra)->exists() ? $obra : null,
            'fase' => FaseTransformacion::tryFrom((string) $request->query('fase'))?->value,
            'inspector' => $request->integer('inspector') ?: null,
            'estatus' => $que === 'pza' ? EstatusInspeccion::tryFrom((string) $request->query('estatus'))?->value : null,
            'desde' => $desde,
            'hasta' => $hasta,
            'buscar' => $buscar === '' ? null : mb_strtoupper($buscar),
            'sort_by' => in_array($request->query('sort_by'), $orden, true) ? (string) $request->query('sort_by') : 'fecha',
            'sort_dir' => $request->query('sort_dir') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** Una fecha `AAAA-MM-DD` que exista en el calendario, o nada. */
    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)) {
            return null;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $valor : null;
    }

    /**
     * Sólo quien tiene algo capturado: un inspector sin registros daría una
     * tabla vacía. El estado sale de su usuario, que es donde se da de baja.
     *
     * @return Collection<int, array{id: int, nombre: string, activo: bool}>
     */
    private function inspectores(): Collection
    {
        return Inspector::query()
            ->whereIn('id', Inspeccion::query()->select('inspector_id'))
            ->orWhereIn('id', Sublote::query()->select('inspector_id'))
            ->with('usuario:id,name,activo')
            ->get()
            ->map(fn (Inspector $inspector): array => [
                'id' => $inspector->id,
                'nombre' => $inspector->usuario?->name ?? '—',
                'activo' => (bool) $inspector->usuario?->activo,
            ])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Inspeccion>
     */
    private function inspecciones(array $filtros): Builder
    {
        return Inspeccion::query()
            ->filtrada($filtros)
            ->when($filtros['desde'], fn (Builder $consulta, string $desde) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'], fn (Builder $consulta, string $hasta) => $consulta->whereDate('fecha', '<=', $hasta));
    }

    /**
     * La última inspección de cada sublote que cae en los filtros.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Sublote>
     */
    private function sublotesVigentes(array $filtros): Builder
    {
        return Sublote::query()
            ->ultimas()
            ->filtrado($filtros)
            ->when($filtros['desde'], fn (Builder $consulta, string $desde) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'], fn (Builder $consulta, string $hasta) => $consulta->whereDate('fecha', '<=', $hasta));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function piezas(array $filtros): LengthAwarePaginator
    {
        return $this->inspecciones($filtros)
            ->with(['obra:id,no', 'inspector.usuario:id,name'])
            ->orderBy($filtros['sort_by'], $filtros['sort_dir'])
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Inspeccion $inspeccion): array => [
                'id' => $inspeccion->id,
                'folio' => $inspeccion->folio,
                'fecha' => $inspeccion->fecha->toDateString(),
                'fase' => $inspeccion->fase->value,
                'etapa' => $inspeccion->subetapa?->etiqueta() ?? $inspeccion->subtipo?->etiqueta(),
                'obra' => $inspeccion->obra?->no,
                'marca' => $inspeccion->marca,
                'lote' => $inspeccion->lote,
                'qr' => $inspeccion->qr,
                'consecutivo' => $inspeccion->consecutivo,
                'numero_inspeccion' => $inspeccion->numero_inspeccion,
                'modulo' => $inspeccion->modulo,
                'inspector' => $inspeccion->inspector?->usuario?->name,
                'estatus' => $inspeccion->estatus->value,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function sublotes(array $filtros): LengthAwarePaginator
    {
        return $this->sublotesVigentes($filtros)
            ->with(['lote:id,obra_id,marca,total_unidades', 'lote.obra:id,no', 'inspector.usuario:id,name'])
            ->orderBy($filtros['sort_by'], $filtros['sort_dir'])
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Sublote $sublote): array => [
                'id' => $sublote->id,
                'fecha' => $sublote->fecha->toDateString(),
                'fase' => $sublote->fase->value,
                'obra' => $sublote->lote->obra?->no,
                'marca' => $sublote->lote->marca,
                'total_lote' => $sublote->lote->total_unidades,
                'unidades' => $sublote->unidades,
                'nivel' => $sublote->nivel->value,
                'muestra' => $sublote->muestra,
                'rechazadas' => $sublote->rechazadas,
                'numero_inspeccion' => $sublote->numero_inspeccion,
                'veredicto' => $sublote->veredicto?->value,
                'disposicion' => $sublote->disposicion,
                'liberado' => $sublote->liberado(),
                'sin_disposicion' => $sublote->sinDisposicion(),
                'inspector' => $sublote->inspector?->usuario?->name,
            ]);
    }

    /**
     * Registro ≠ pieza: una pieza reinspeccionada tres veces son tres
     * registros y una sola pieza.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{registros: int, piezas: int|null, total: int}
     */
    private function conteo(array $filtros): array
    {
        if ($filtros['que'] === 'acc') {
            return [
                'registros' => $this->sublotesVigentes($filtros)->count(),
                'piezas' => null,
                'total' => Sublote::query()->ultimas()->count(),
            ];
        }

        $piezas = $this->inspecciones($filtros)
            ->select(['obra_id', 'marca', 'lote', 'consecutivo', 'qr'])
            ->distinct();

        return [
            'registros' => $this->inspecciones($filtros)->count(),
            'piezas' => Inspeccion::query()->fromSub($piezas, 'piezas')->count(),
            'total' => Inspeccion::query()->count(),
        ];
    }
}
