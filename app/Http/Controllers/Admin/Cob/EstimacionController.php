<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\CambiarEstadoEstimacionRequest;
use App\Http\Requests\Admin\Cob\EstimacionStoreRequest;
use App\Http\Requests\Admin\Cob\EstimacionUpdateRequest;
use App\Models\Cob\Estimacion;
use App\Models\Cob\TipoRetencion;
use App\Models\Obra;
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

    public function create(Obra $obra): Response
    {
        $nextNumber = ($obra->estimaciones()->max('numero_estimacion') ?? 0) + 1;

        return Inertia::render('admin/cob/estimaciones/create', [
            'obra' => $obra,
            'nextNumber' => $nextNumber,
        ]);
    }

    public function store(EstimacionStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->estimaciones()->create([
            ...$request->validated(),
            'proyecto_id' => $obra->proyecto_id,
        ]);

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Estimacion $estimacion): Response
    {
        $estimacion->load(['pagos', 'historial.usuario', 'retenciones.tipoRetencion', 'documentos.configuracionDocumento']);
        $tiposRetencion = TipoRetencion::all();

        return Inertia::render('admin/cob/estimaciones/edit', [
            'obra' => $obra,
            'estimacion' => $estimacion,
            'tiposRetencion' => $tiposRetencion,
        ]);
    }

    public function update(EstimacionUpdateRequest $request, Obra $obra, Estimacion $estimacion): RedirectResponse
    {
        $estimacion->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Estimacion $estimacion): RedirectResponse
    {
        if ($estimacion->pagos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una estimacion con pagos registrados.']);
        }

        $estimacion->delete();

        return to_route('admin.cob.obras.show', $obra);
    }

    public function cambiarEstado(CambiarEstadoEstimacionRequest $request, Obra $obra, Estimacion $estimacion): RedirectResponse
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
