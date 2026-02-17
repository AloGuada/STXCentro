<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\AprobacionSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AprobacionController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $baseQuery = fn () => AprobacionSolicitud::where('aprobador_id', $userId)
            ->with(['solicitud.departamento', 'solicitud.proveedor', 'solicitud.solicitante']);

        // Pendientes: estatus='pendiente' y es el turno del aprobador
        $pendientes = $baseQuery()
            ->where('estatus', 'pendiente')
            ->get()
            ->filter(function (AprobacionSolicitud $aprobacion) {
                // Es mi turno si no hay aprobaciones pendientes con nivel menor en la misma solicitud
                return ! AprobacionSolicitud::where('solicitud_id', $aprobacion->solicitud_id)
                    ->where('estatus', 'pendiente')
                    ->where('nivel', '<', $aprobacion->nivel)
                    ->exists();
            })
            ->values();

        $aprobadas = $baseQuery()
            ->where('estatus', 'aprobada')
            ->latest('fecha_respuesta')
            ->get();

        $rechazadas = $baseQuery()
            ->where('estatus', 'rechazada')
            ->latest('fecha_respuesta')
            ->get();

        return Inertia::render('admin/costos/aprobaciones/index', [
            'pendientes' => $pendientes,
            'aprobadas' => $aprobadas,
            'rechazadas' => $rechazadas,
        ]);
    }

    public function show(AprobacionSolicitud $aprobacionSolicitud): Response|HttpResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        $aprobacionSolicitud->load('solicitud');
        $solicitud = $aprobacionSolicitud->solicitud;

        $solicitud->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud.documentos',
            'detalles.obraRubro.rubro',
            'archivos.documento',
            'aprobaciones.aprobador',
        ]);

        return Inertia::render('admin/costos/aprobaciones/show', [
            'aprobacion' => $aprobacionSolicitud,
            'solicitud' => $solicitud,
        ]);
    }

    public function aprobar(Request $request, AprobacionSolicitud $aprobacionSolicitud): RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if ($aprobacionSolicitud->estatus !== 'pendiente') {
            return back()->withErrors(['estatus' => 'Esta aprobación ya fue procesada.']);
        }

        // Verificar que es el turno
        $hayPendientesAnteriores = AprobacionSolicitud::where('solicitud_id', $aprobacionSolicitud->solicitud_id)
            ->where('estatus', 'pendiente')
            ->where('nivel', '<', $aprobacionSolicitud->nivel)
            ->exists();

        if ($hayPendientesAnteriores) {
            return back()->withErrors(['nivel' => 'Aún hay aprobaciones pendientes de niveles anteriores.']);
        }

        $request->validate([
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $aprobacionSolicitud->update([
            'estatus' => 'aprobada',
            'fecha_respuesta' => now(),
            'observaciones' => $request->input('observaciones'),
        ]);

        // Verificar si era el último nivel pendiente
        $solicitud = $aprobacionSolicitud->solicitud;
        $quedanPendientes = $solicitud->aprobaciones()
            ->where('estatus', 'pendiente')
            ->exists();

        if (! $quedanPendientes) {
            $solicitud->update(['estatus' => 'aprobada']);
            $solicitud->aplicarImpactoPresupuestal(auth()->id());
        }

        return back()->with('success', 'Aprobación registrada correctamente.');
    }

    public function rechazar(Request $request, AprobacionSolicitud $aprobacionSolicitud): RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if ($aprobacionSolicitud->estatus !== 'pendiente') {
            return back()->withErrors(['estatus' => 'Esta aprobación ya fue procesada.']);
        }

        $request->validate([
            'observaciones' => ['required', 'string', 'max:500'],
        ]);

        $aprobacionSolicitud->update([
            'estatus' => 'rechazada',
            'fecha_respuesta' => now(),
            'observaciones' => $request->input('observaciones'),
        ]);

        // Cancelar la solicitud y las demás aprobaciones pendientes
        $solicitud = $aprobacionSolicitud->solicitud;
        $solicitud->update(['estatus' => 'cancelada']);

        $solicitud->aprobaciones()
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        return back()->with('success', 'Solicitud rechazada.');
    }
}
