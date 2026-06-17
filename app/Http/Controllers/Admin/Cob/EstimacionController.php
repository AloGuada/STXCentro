<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\CambiarEstadoEstimacionRequest;
use App\Http\Requests\Admin\Cob\EstimacionStoreRequest;
use App\Http\Requests\Admin\Cob\EstimacionUpdateRequest;
use App\Models\Cob\Estimacion;
use App\Models\Cob\TipoRetencion;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimacionController extends Controller
{
    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        'pendiente' => ['generada'],
        'generada' => ['ingresada'],
        'ingresada' => ['revisada'],
        'revisada' => ['autorizada'],
        'autorizada' => ['facturada'],
        'facturada' => ['pago_parcial', 'pagado'],
        'pago_parcial' => ['pagado'],
        'pagado' => [],
    ];

    public function create(Proyecto $proyecto): Response
    {
        $nextNumber = ($proyecto->estimaciones()->max('numero_estimacion') ?? 0) + 1;

        return Inertia::render('admin/cob/estimaciones/create', [
            'proyecto' => $proyecto,
            'nextNumber' => $nextNumber,
        ]);
    }

    public function store(EstimacionStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->estimaciones()->create([
            ...$request->validated(),
            // obra_id de transición = obra base del proyecto (se dropea en Fase 5).
            'obra_id' => $proyecto->obrasBase()->value('id'),
        ]);

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function edit(Proyecto $proyecto, Estimacion $estimacion): Response
    {
        $estimacion->load(['pagos', 'historial.usuario', 'retenciones.tipoRetencion', 'documentos.configuracionDocumento']);
        $tiposRetencion = TipoRetencion::all();

        return Inertia::render('admin/cob/estimaciones/edit', [
            'proyecto' => $proyecto,
            'estimacion' => $estimacion,
            'tiposRetencion' => $tiposRetencion,
        ]);
    }

    public function update(EstimacionUpdateRequest $request, Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        $estimacion->update($request->validated());

        return back();
    }

    public function destroy(Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        if ($estimacion->pagos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una estimacion con pagos registrados.']);
        }

        $estimacion->delete();

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function cambiarEstado(CambiarEstadoEstimacionRequest $request, Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        $estadoActual = $estimacion->estado;
        $estadoNuevo = $request->validated('estado');

        $transicionesPermitidas = self::TRANSICIONES[$estadoActual] ?? [];

        if (! in_array($estadoNuevo, $transicionesPermitidas)) {
            return back()->withErrors(['estado' => "No se puede cambiar de '{$estadoActual}' a '{$estadoNuevo}'."]);
        }

        $fechaCambio = $request->validated('fecha_cambio') ?? now();

        DB::transaction(function () use ($estimacion, $estadoActual, $estadoNuevo, $request, $fechaCambio) {
            $estimacion->update([
                'estado' => $estadoNuevo,
                'fecha_ultimo_cambio_estado' => $fechaCambio,
            ]);

            $estimacion->historial()->create([
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $estadoNuevo,
                'folio' => $request->validated('folio'),
                'usuario_id' => auth()->id(),
                'comentario' => $request->validated('comentario'),
                'fecha_cambio' => $fechaCambio,
            ]);
        });

        return back();
    }
}
