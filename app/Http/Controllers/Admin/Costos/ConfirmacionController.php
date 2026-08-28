<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Exports\Costos\ConfirmacionesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\ConfirmacionesReporteRequest;
use App\Services\Costos\PuntosDeControl;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConfirmacionController extends Controller
{
    public function __construct(private PuntosDeControl $puntosDeControl) {}

    /**
     * Bandeja "Por confirmar": puntos de control de Costos y Contabilidad que
     * quedan tras la cadena de firmas. A diferencia de "Mis Aprobaciones", no
     * exige firma configurada y filtra por permiso, no por asignación.
     */
    public function index(): Response
    {
        ['costos' => $costos, 'contabilidad' => $contabilidad] = $this->puntosDeControl->paraUsuario(auth()->user());

        return Inertia::render('admin/costos/confirmaciones/index', [
            'costos' => $costos,
            'contabilidad' => $contabilidad,
        ]);
    }

    /**
     * Reporte en Excel de una de las dos bandejas, con las mismas filas que la
     * pantalla: se piden al mismo servicio, así que respeta los permisos del
     * usuario sin poder divergir de lo que ve. Si la pantalla trae un filtro
     * activo, llega en `q` y se aplica aquí con el mismo criterio, para que el
     * archivo siga siendo lo que se ve y no la bandeja completa.
     */
    public function exportar(ConfirmacionesReporteRequest $request): BinaryFileResponse
    {
        $paso = $request->validated('paso');
        $filas = $this->puntosDeControl->filtrar(
            $this->puntosDeControl->paraUsuario($request->user())[$paso],
            $request->validated('q'),
        );

        return Excel::download(
            new ConfirmacionesExport($filas, $paso),
            sprintf('por-confirmar-%s-%s.xlsx', $paso, now()->format('Ymd')),
        );
    }
}
