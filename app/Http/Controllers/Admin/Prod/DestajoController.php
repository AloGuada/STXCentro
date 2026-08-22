<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\DestajoStoreRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Proceso;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Services\Prod\AsistenciaDelDestajo;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\GeneradorLiquidaciones;
use App\Services\Prod\PendientesDeLiquidar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DestajoController extends Controller
{
    public function index(Request $request): Response
    {
        $destajos = Destajo::query()
            ->withCount('liquidaciones')
            ->when($request->search, fn ($q, $s) => $q->where('semana', 'like', "%{$s}%")->orWhere('anio', 'like', "%{$s}%"))
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/destajos/index', [
            'destajos' => $destajos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/destajos/create', [
            'anioSugerido' => now()->year,
            'semanaSugerida' => min(now()->weekOfYear, 52),
        ]);
    }

    public function store(DestajoStoreRequest $request): RedirectResponse
    {
        $destajo = Destajo::create($request->validated());

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function show(
        Destajo $destajo,
        GeneradorLiquidaciones $generador,
        PendientesDeLiquidar $pendientes,
        AsistenciaDelDestajo $asistencia,
    ): Response {
        $destajo->load([
            'liquidaciones.grupoTrabajo',
            'liquidaciones.detalles',
            'liquidaciones.empleados',
            'liquidaciones.generador',
        ]);

        $data = ['destajo' => $destajo];

        if ($destajo->cerrado) {
            return Inertia::render('admin/prod/destajos/show', $data);
        }

        $data['registrosPreview'] = Registro::query()
            ->with(['pieza.marca.obra', 'proceso', 'grupoTrabajo'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->orderByDesc('fecha')
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['pagosExtraPreview'] = PagoExtra::query()
            ->with(['tipo', 'grupoTrabajo'])
            ->where('destajo_id', $destajo->id)
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['piezasSinPrecio'] = $generador->piezasSinPrecio($destajo);
        $data['gruposTrabajo'] = GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get();
        // El catalogo de captura: solo las marcas del catalogo vigente, sin sus
        // piezas. Una obra grande son decenas de miles de piezas y mandarlas
        // todas para usar las de una sola marca eran 17 MB de respuesta y cientos
        // de MB de memoria; las piezas de la marca elegida se piden aparte
        // (`piezasDeMarca`), que es como el formulario las usa: marca -> QR.
        $data['marcas'] = Concepto::query()
            ->with('obra:id,no,descripcion')
            ->deCatalogoVigente()
            ->where('activo', true)
            ->orderBy('marca')
            ->orderBy('lote')
            ->get(['id', 'obra_id', 'marca', 'lote', 'descripcion', 'cantidad']);

        $data['procesos'] = Proceso::activos()->orderBy('orden')->get();
        $data['procesosPorObra'] = Obra::query()
            ->whereIn('id', $data['marcas']->pluck('obra_id')->unique())
            ->with('procesos:id')
            ->get()
            ->mapWithKeys(fn (Obra $obra) => [$obra->id => $obra->procesos->pluck('id')]);

        $data['tipos'] = TipoPagoExtra::orderBy('orden')->get();
        $data['pendientes'] = $pendientes->paraDestajo($destajo);
        $data['asistenciaFaltante'] = $asistencia->faltantes($destajo);

        return Inertia::render('admin/prod/destajos/show', $data);
    }

    /**
     * Las piezas de una marca con su avance por proceso, para el formulario de
     * captura. Se piden al elegir la marca en vez de venir en el `show`: es lo
     * que evita cargar el catalogo entero de la obra en cada visita.
     */
    public function piezasDeMarca(Destajo $destajo, Concepto $concepto, AvanceDePiezas $avance): JsonResponse
    {
        $piezas = $concepto->piezas()
            ->where('activo', true)
            ->orderBy('qr')
            ->get(['id', 'catalogo_id', 'concepto_id', 'qr', 'qs']);

        $procesoIds = Proceso::activos()->pluck('id')->all();

        return response()->json([
            'piezas' => $avance->decorar($piezas, $procesoIds)->map(fn ($pieza) => [
                'id' => $pieza->id,
                'qr' => $pieza->qr,
                'qs' => $pieza->qs,
                'avance' => $pieza->avance,
            ])->values(),
        ]);
    }

    public function destroy(Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un destajo cerrado.']);
        }

        $destajo->liquidaciones()->delete();
        $destajo->pagosExtra()->delete();
        $destajo->delete();

        return to_route('admin.prod.destajos.index');
    }

    public function ordenPagoPdf(Destajo $destajo, GeneradorLiquidaciones $generador): HttpResponse
    {
        $grupos = $generador->ordenDePago($destajo);

        $pdf = Pdf::loadView('pdf.prod.orden-pago', [
            'destajo' => $destajo,
            'grupos' => $grupos,
        ])->setPaper('letter', 'landscape');

        return $pdf->stream("orden-pago-{$destajo->anio}-S{$destajo->semana}.pdf");
    }

    public function cerrar(
        Destajo $destajo,
        GeneradorLiquidaciones $generador,
        AsistenciaDelDestajo $asistencia,
    ): RedirectResponse {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'Este destajo ya esta cerrado.']);
        }

        // Parte del pago va a salario base y depende de los dias trabajados:
        // sin asistencia completa no se puede liquidar la semana.
        $faltantes = $asistencia->faltantes($destajo);

        if ($faltantes->isNotEmpty()) {
            $detalle = $faltantes
                ->map(fn (array $f) => $f['grupo'].' ('.implode(', ', $f['empleados']).')')
                ->implode('; ');

            return back()->withErrors([
                'error' => "Falta capturar la asistencia antes de cerrar: {$detalle}.",
            ]);
        }

        $generador->generar($destajo);

        return back();
    }
}
