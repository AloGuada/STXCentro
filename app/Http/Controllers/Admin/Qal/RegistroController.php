<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Exports\Qal\InspeccionesExport;
use App\Exports\Qal\SublotesExport;
use App\Http\Controllers\Controller;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\Obra;
use App\Models\Qal\Sublote;
use App\Services\Qal\FichaDeRegistro;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Registros: la base de lo capturado, en crudo. El `Registros_Steelex.html` de
 * la aplicación anterior.
 *
 * No es un reporte, es la tabla de auditoría: aquí se responde «¿esta pieza se
 * inspeccionó?, ¿quién la liberó?, ¿por qué se rechazó en julio?». Por eso no
 * resume nada; los resúmenes están en el tablero.
 *
 * Piezas y sublotes de accesorios comparten la pantalla pero no la tabla: no
 * tienen ni las mismas columnas ni la misma unidad de conteo. De los sublotes
 * se lista la última inspección de cada uno, que es la que manda.
 */
class RegistroController extends Controller
{
    private const POR_PAGINA = 50;

    private const ORDEN_PIEZAS = ['fecha', 'fase', 'marca', 'consecutivo', 'numero_inspeccion', 'modulo', 'estatus'];

    private const ORDEN_SUBLOTES = ['fecha', 'unidades', 'muestra', 'rechazadas', 'veredicto', 'numero_inspeccion'];

    public function index(Request $request, FichaDeRegistro $fichas): Response
    {
        $filtros = $this->filtros($request);
        $esPiezas = $filtros['que'] === 'pza';

        return Inertia::render('admin/calidad/registros/index', [
            'filtros' => $filtros,
            'obras' => fn () => Obra::opcionesDeSelector(soloActivas: false),
            'inspectores' => fn () => Inspector::query()
                ->with('usuario:id,name')
                ->get()
                ->map(fn (Inspector $inspector): array => ['id' => $inspector->id, 'nombre' => $inspector->usuario?->name ?? '—'])
                ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
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
     * La exportación saca exactamente lo que filtra la pantalla. Es permiso
     * aparte (RF-18.3): exportar es sacar la información del sistema.
     */
    public function exportar(Request $request): BinaryFileResponse
    {
        $filtros = $this->filtros($request);
        $fecha = now()->format('Ymd');

        return $filtros['que'] === 'acc'
            ? Excel::download(new SublotesExport($filtros), "calidad-sublotes-{$fecha}.xlsx")
            : Excel::download(new InspeccionesExport($filtros), "calidad-inspecciones-{$fecha}.xlsx");
    }

    /**
     * Lo que no se reconoce se descarta en vez de filtrar por ello: una fase
     * inventada en la URL daría una tabla vacía sin explicación.
     *
     * @return array{que: string, obra: int|null, fase: string|null, inspector: int|null, estatus: string|null, fecha: string|null, buscar: string|null, sort_by: string, sort_dir: string}
     */
    private function filtros(Request $request): array
    {
        $que = $request->query('que') === 'acc' ? 'acc' : 'pza';
        $orden = $que === 'acc' ? self::ORDEN_SUBLOTES : self::ORDEN_PIEZAS;
        $fecha = (string) $request->query('fecha', '');
        $buscar = trim((string) $request->query('buscar', ''));

        return [
            'que' => $que,
            'obra' => $request->integer('obra') ?: null,
            'fase' => $que === 'pza' ? FaseTransformacion::tryFrom((string) $request->query('fase'))?->value : null,
            'inspector' => $request->integer('inspector') ?: null,
            'estatus' => $que === 'pza' ? EstatusInspeccion::tryFrom((string) $request->query('estatus'))?->value : null,
            'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null,
            'buscar' => $buscar === '' ? null : mb_strtoupper($buscar),
            'sort_by' => in_array($request->query('sort_by'), $orden, true) ? (string) $request->query('sort_by') : 'fecha',
            'sort_dir' => $request->query('sort_dir') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function piezas(array $filtros): LengthAwarePaginator
    {
        return Inspeccion::query()
            ->filtrada($filtros)
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
        return Sublote::query()
            ->ultimas()
            ->filtrado($filtros)
            ->with(['lote:id,obra_id,marca,total_unidades', 'lote.obra:id,no', 'inspector.usuario:id,name'])
            ->orderBy($filtros['sort_by'], $filtros['sort_dir'])
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Sublote $sublote): array => [
                'id' => $sublote->id,
                'fecha' => $sublote->fecha->toDateString(),
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
     * registros y una sola pieza. Llamarlos «piezas» hacía creer que se había
     * inspeccionado el triple de lo real.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{registros: int, piezas: int|null, total: int}
     */
    private function conteo(array $filtros): array
    {
        if ($filtros['que'] === 'acc') {
            return [
                'registros' => Sublote::query()->ultimas()->filtrado($filtros)->count(),
                'piezas' => null,
                'total' => Sublote::query()->ultimas()->count(),
            ];
        }

        $piezas = Inspeccion::query()
            ->filtrada($filtros)
            ->select(['obra_id', 'marca', 'lote', 'consecutivo', 'qr'])
            ->distinct();

        return [
            'registros' => Inspeccion::query()->filtrada($filtros)->count(),
            'piezas' => Inspeccion::query()->fromSub($piezas, 'piezas')->count(),
            'total' => Inspeccion::query()->count(),
        ];
    }
}
