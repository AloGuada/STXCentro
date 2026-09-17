<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ReporteRequest;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\Obra;
use App\Services\Qal\Formatos\Formato;
use App\Services\Qal\Formatos\Formatos;
use App\Services\Qal\Formatos\GeneradorDeFormato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 * Los formatos PDF F-STX-* de Calidad: la pantalla que los previsualiza y el
 * PDF de cada uno.
 *
 * La pantalla sólo elige; el documento lo arma el servidor, que es donde está
 * la regla del dosier. Por eso la previsualización es el mismo PDF que se
 * descarga, no una copia en HTML que podría separarse de él.
 */
class ReporteController extends Controller
{
    public function __construct(private Formatos $formatos) {}

    public function index(Request $request): Response
    {
        $formato = $this->formatos->de((string) $request->query('formato')) ?? $this->formatos->todos()[0];
        $obra = $request->integer('obra') ?: null;

        return Inertia::render('admin/calidad/reportes/index', [
            'formatos' => array_map(fn (Formato $f): array => $f->ficha(), $this->formatos->todos()),
            'obras' => Obra::opcionesDeSelector(soloActivas: false),
            'filtros' => [
                'formato' => $formato->clave(),
                'obra' => $obra ? (string) $obra : '',
                'periodo' => in_array($request->query('periodo'), ['dia', 'semana', 'todo'], true) ? $request->query('periodo') : 'todo',
                'fecha' => (string) $request->query('fecha', ''),
                'semana' => (string) $request->query('semana', ''),
                'inspector' => (string) $request->query('inspector', ''),
                'estatus' => (string) $request->query('estatus', $formato->estatusPorDefecto()),
                'vista' => (string) $request->query('vista', $formato->vistaPorDefecto()),
                'pieza' => (string) $request->query('pieza', ''),
            ],
            'opciones' => $obra ? $this->opciones($formato, $obra) : null,
        ]);
    }

    public function pdf(ReporteRequest $request, string $formato, GeneradorDeFormato $generador): RespuestaHttp
    {
        $formato = $this->formatos->de($formato);
        abort_if($formato === null, 404);

        $filtros = $request->filtros($formato);
        $pdf = $generador->pdf($formato, $filtros);
        $nombre = Str::slug($formato->codigo().' '.$formato->titulo()).'.pdf';

        return $request->boolean('descargar') ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    /**
     * Lo que se puede elegir en la obra para ese formato: sólo días, semanas e
     * inspectores con inspecciones de su etapa, y en el mapeo las piezas con
     * juntas.
     *
     * @return array{total: int, fechas: list<string>, semanas: list<string>, inspectores: list<array{valor: string, texto: string}>, piezas: list<array{valor: string, texto: string}>}
     */
    private function opciones(Formato $formato, int $obra): array
    {
        $base = fn (): Builder => Inspeccion::query()
            ->where('obra_id', $obra)
            ->where('fase', $formato->fase()->value)
            ->when($formato->usaPeriodo() ? $formato->subetapa() : null, fn (Builder $consulta, $subetapa) => $consulta->where('subetapa', $subetapa->value));

        return [
            'total' => $base()->count(),
            'fechas' => $base()->distinct()->orderByDesc('fecha')->pluck('fecha')
                ->map(fn ($fecha): string => CarbonImmutable::parse($fecha)->toDateString())->unique()->values()->all(),
            'semanas' => $base()->select(['anio', 'semana'])->distinct()->orderByDesc('anio')->orderByDesc('semana')->get()
                ->map(fn (Inspeccion $fila): string => sprintf('%d-S%02d', $fila->anio, $fila->semana))->values()->all(),
            'inspectores' => Inspector::query()
                ->whereIn('id', $base()->select('inspector_id'))
                ->with('usuario:id,name')
                ->get()
                ->map(fn (Inspector $inspector): array => ['valor' => (string) $inspector->id, 'texto' => $inspector->usuario?->name ?? "Inspector {$inspector->id}"])
                ->sortBy('texto')
                ->values()
                ->all(),
            'piezas' => $formato->usaPeriodo() ? [] : $base()
                ->where('subetapa', $formato->subetapa()?->value)
                ->whereNotNull('prod_pieza_id')
                ->whereHas('juntas')
                ->get(['prod_pieza_id', 'marca', 'qr'])
                ->unique('prod_pieza_id')
                ->sort(fn (Inspeccion $a, Inspeccion $b): int => strnatcasecmp($a->marca, $b->marca) ?: strnatcmp((string) $a->qr, (string) $b->qr))
                ->map(fn (Inspeccion $fila): array => ['valor' => (string) $fila->prod_pieza_id, 'texto' => "{$fila->marca} · QR {$fila->qr}"])
                ->values()
                ->all(),
        ];
    }
}
