<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Models\Cotiz\Obra;
use App\Services\Cotiz\ResumenCalculator;
use App\Services\Cotiz\ResumenColumnaSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carátula de Cotización (Fase 5): resumen ejecutivo de la obra — columnas con sus
 * kilos/importe y el importe total de venta derivado del Resumen de Proyecto.
 *
 * En prepsim la carátula quedó como placeholder; aquí se entrega una vista de totales
 * con export a PDF (barryvdh/laravel-dompdf, ya disponible en el mono).
 */
class CaratulaController extends Controller
{
    public function index(Obra $obra, ResumenCalculator $calculator, ResumenColumnaSync $sync): Response
    {
        $datos = $this->datos($obra, $calculator, $sync);

        return Inertia::render('admin/cotiz/caratula/index', $datos);
    }

    public function pdf(Obra $obra, ResumenCalculator $calculator, ResumenColumnaSync $sync): HttpResponse
    {
        $datos = $this->datos($obra, $calculator, $sync);

        $pdf = Pdf::loadView('pdf.cotiz.caratula', $datos)->setPaper('letter', 'portrait');

        return $pdf->download('caratula-'.str($obra->nombre)->slug().'.pdf');
    }

    /**
     * Sincroniza columnas y arma el payload del resumen ejecutivo (compartido por vista y PDF).
     *
     * @return array<string, mixed>
     */
    private function datos(Obra $obra, ResumenCalculator $calculator, ResumenColumnaSync $sync): array
    {
        $sync->sincronizar($obra);
        $resultado = $calculator->calcular($obra->fresh());

        return [
            'obra' => $obra->only(['id', 'nombre', 'op']),
            'columnas' => collect($resultado['columnas'])->map(fn (array $c) => [
                'columna_id' => $c['columna_id'],
                'nombre' => $c['nombre'],
                'kg' => $c['kg'],
                'm2_pintura' => $c['m2_pintura'],
                'importe_materiales' => $c['importe_materiales'],
            ])->values(),
            'obraTotales' => $resultado['obra_totales'],
            'importeTotalVenta' => $resultado['importe_total_venta'],
        ];
    }
}
