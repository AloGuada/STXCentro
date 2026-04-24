<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Models\Costos\AprobacionSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AprobacionController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        if (! auth()->user()->firma_path) {
            return redirect()->route('admin.costos.firma.edit')
                ->with('warning', 'Debe configurar su firma antes de acceder a las aprobaciones.');
        }

        $userId = auth()->id();

        $baseQuery = fn () => AprobacionSolicitud::where('aprobador_id', $userId)
            ->with(['solicitud.departamento', 'solicitud.proveedor', 'solicitud.solicitante', 'solicitud.tipoSolicitud', 'solicitud.archivos', 'solicitud.media', 'solicitud.detalles.obraRubro']);

        // Pendientes: estatus='pendiente' y es el turno del aprobador
        $pendientes = $baseQuery()
            ->where('estatus', 'pendiente')
            ->latest()
            ->get()
            ->filter(function (AprobacionSolicitud $aprobacion) {
                // Es mi turno si todos los niveles anteriores tienen al menos una aprobación
                $nivelesAnteriores = AprobacionSolicitud::where('solicitud_id', $aprobacion->solicitud_id)
                    ->where('nivel', '<', $aprobacion->nivel)
                    ->select('nivel')
                    ->distinct()
                    ->pluck('nivel');

                foreach ($nivelesAnteriores as $nivel) {
                    $tieneAprobada = AprobacionSolicitud::where('solicitud_id', $aprobacion->solicitud_id)
                        ->where('nivel', $nivel)
                        ->where('estatus', 'aprobada')
                        ->exists();

                    if (! $tieneAprobada) {
                        return false;
                    }
                }

                return true;
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

    public function show(AprobacionSolicitud $aprobacionSolicitud): Response|HttpResponse|RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if (! auth()->user()->firma_path) {
            return redirect()->route('admin.costos.firma.edit')
                ->with('warning', 'Debe configurar su firma antes de acceder a las aprobaciones.');
        }

        $aprobacionSolicitud->load('solicitud');
        $solicitud = $aprobacionSolicitud->solicitud;

        $solicitud->load([
            'solicitante',
            'departamento',
            'proveedor',
            'tipoSolicitud.documentos',
            'detalles.obraRubro.rubro',
            'detalles.obraRubro.obra',
            'archivos.documento',
            'archivos.media',
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

        if ($aprobacionSolicitud->estatus !== AprobacionEstatus::Pendiente) {
            return back()->withErrors(['estatus' => 'Esta aprobación ya fue procesada.']);
        }

        // Verificar que es el turno (todos los niveles anteriores deben tener al menos una aprobada)
        $nivelesAnteriores = AprobacionSolicitud::where('solicitud_id', $aprobacionSolicitud->solicitud_id)
            ->where('nivel', '<', $aprobacionSolicitud->nivel)
            ->select('nivel')
            ->distinct()
            ->pluck('nivel');

        foreach ($nivelesAnteriores as $nivel) {
            $tieneAprobada = AprobacionSolicitud::where('solicitud_id', $aprobacionSolicitud->solicitud_id)
                ->where('nivel', $nivel)
                ->where('estatus', 'aprobada')
                ->exists();

            if (! $tieneAprobada) {
                return back()->withErrors(['nivel' => 'Aún hay aprobaciones pendientes de niveles anteriores.']);
            }
        }

        $request->validate([
            'observaciones' => ['required', 'string', 'max:500'],
        ]);

        $aprobacionSolicitud->update([
            'estatus' => 'aprobada',
            'fecha_respuesta' => now(),
            'observaciones' => $request->input('observaciones'),
            'ip' => $request->ip(),
            'hostname' => gethostbyaddr($request->ip()) ?: null,
        ]);

        // Cancelar las demás aprobaciones pendientes del mismo nivel (lógica OR)
        $solicitud = $aprobacionSolicitud->solicitud;
        $solicitud->aprobaciones()
            ->where('nivel', $aprobacionSolicitud->nivel)
            ->where('id', '!=', $aprobacionSolicitud->id)
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        // Verificar si quedan niveles pendientes
        $quedanPendientes = $solicitud->aprobaciones()
            ->where('estatus', 'pendiente')
            ->exists();

        if (! $quedanPendientes) {
            $solicitud->transitionTo(SolicitudPagoEstatus::Aprobada);
            $solicitud->aplicarImpactoPresupuestal(auth()->id());
        }

        return back()->with('success', 'Aprobación registrada correctamente.');
    }

    public function rechazar(Request $request, AprobacionSolicitud $aprobacionSolicitud): RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if ($aprobacionSolicitud->estatus !== AprobacionEstatus::Pendiente) {
            return back()->withErrors(['estatus' => 'Esta aprobación ya fue procesada.']);
        }

        $request->validate([
            'observaciones' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'observaciones.required' => 'Debe indicar el motivo del rechazo.',
            'observaciones.min' => 'El motivo del rechazo debe tener al menos 10 caracteres.',
        ]);

        $motivo = $request->input('observaciones');

        $aprobacionSolicitud->update([
            'estatus' => 'rechazada',
            'fecha_respuesta' => now(),
            'observaciones' => $motivo,
            'motivo_rechazo' => $motivo,
            'ip' => $request->ip(),
            'hostname' => gethostbyaddr($request->ip()) ?: null,
        ]);

        // Cancelar la solicitud y las demás aprobaciones pendientes
        $solicitud = $aprobacionSolicitud->solicitud;
        $solicitud->transitionTo(SolicitudPagoEstatus::Cancelada);

        $solicitud->aprobaciones()
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        return back()->with('success', 'Solicitud rechazada.');
    }
}
