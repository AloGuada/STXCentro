<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ReporteNotaRequest;
use App\Models\Cob\ReporteNota;
use App\Models\Obra;
use App\Services\Cob\ReporteCobranzaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReporteCobranzaController extends Controller
{
    public function __construct(private readonly ReporteCobranzaService $service) {}

    public function index(Request $request): Response
    {
        $anioActual = (int) Carbon::now()->isoWeekYear;
        $anio = (int) ($request->integer('anio') ?: $anioActual);

        return Inertia::render('admin/cob/reportes/index', [
            'anio' => $anio,
            'anios' => $this->aniosDisponibles($anioActual),
            'filas' => $this->service->tablaAnual($anio),
        ]);
    }

    public function show(int $anio, int $semana): Response
    {
        abort_unless($semana >= 1 && $semana <= 53, 404);

        return Inertia::render('admin/cob/reportes/show', [
            'reporte' => $this->service->semana($anio, $semana),
        ]);
    }

    public function updateNotas(ReporteNotaRequest $request, int $anio, int $semana): RedirectResponse
    {
        abort_unless($semana >= 1 && $semana <= 53, 404);

        ReporteNota::updateOrCreate(
            ['anio' => $anio, 'semana' => $semana],
            ['notas' => $request->validated('notas')],
        );

        return back();
    }

    public function pdf(int $anio, int $semana): HttpResponse
    {
        abort_unless($semana >= 1 && $semana <= 53, 404);

        $pdf = Pdf::loadView('pdf.cob.reporte-semanal-cobranza', [
            'r' => $this->service->semana($anio, $semana),
        ])->setPaper('letter', 'portrait');

        return $pdf->download("reporte-cobranza-{$anio}-S{$semana}.pdf");
    }

    /**
     * Años con datos (desde la obra más antigua) hasta el año en curso.
     *
     * @return list<int>
     */
    private function aniosDisponibles(int $anioActual): array
    {
        $primera = Obra::query()->sinPlanta()->min('created_at');
        $anioInicio = $primera ? (int) Carbon::parse($primera)->isoWeekYear : $anioActual;

        return range($anioActual, min($anioInicio, $anioActual));
    }
}
