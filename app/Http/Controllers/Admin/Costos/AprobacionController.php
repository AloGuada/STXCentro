<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Http\Controllers\Controller;
use App\Models\Costos\Aprobacion;
use App\Models\Costos\SolicitudPago;
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

        // Solo SolicitudPago se muestra hoy en este index. Otros tipos
        // (Requisicion, etc.) tienen su propio listado en su modulo.
        $baseQuery = fn () => Aprobacion::where('aprobador_id', $userId)
            ->where('aprobable_type', SolicitudPago::class)
            ->with(['aprobable.departamento', 'aprobable.proveedor', 'aprobable.solicitante', 'aprobable.tipoSolicitud', 'aprobable.archivos', 'aprobable.media', 'aprobable.detalles.obraRubro']);

        $pendientes = $baseQuery()
            ->where('estatus', 'pendiente')
            ->latest()
            ->get()
            ->filter(fn (Aprobacion $a) => $this->esTurno($a))
            ->values();

        $aprobadas = $baseQuery()
            ->where('estatus', 'aprobada')
            ->latest('fecha_respuesta')
            ->get();

        $rechazadas = $baseQuery()
            ->where('estatus', 'rechazada')
            ->latest('fecha_respuesta')
            ->get();

        // Para vistas legacy expone tambien `solicitud` cargado.
        $pendientes->loadMissing('aprobable.solicitante');

        return Inertia::render('admin/costos/aprobaciones/index', [
            'pendientes' => $pendientes->map(fn (Aprobacion $a) => $this->withSolicitudShim($a)),
            'aprobadas' => $aprobadas->map(fn (Aprobacion $a) => $this->withSolicitudShim($a)),
            'rechazadas' => $rechazadas->map(fn (Aprobacion $a) => $this->withSolicitudShim($a)),
        ]);
    }

    public function show(Aprobacion $aprobacionSolicitud): Response|HttpResponse|RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if (! auth()->user()->firma_path) {
            return redirect()->route('admin.costos.firma.edit')
                ->with('warning', 'Debe configurar su firma antes de acceder a las aprobaciones.');
        }

        $aprobacionSolicitud->load('aprobable');
        $aprobable = $aprobacionSolicitud->aprobable;

        if ($aprobable instanceof SolicitudPago) {
            $aprobable->load([
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
        }

        return Inertia::render('admin/costos/aprobaciones/show', [
            'aprobacion' => $this->withSolicitudShim($aprobacionSolicitud),
            'solicitud' => $aprobable,
        ]);
    }

    public function aprobar(Request $request, Aprobacion $aprobacionSolicitud): RedirectResponse
    {
        if ($aprobacionSolicitud->aprobador_id !== auth()->id()) {
            abort(403);
        }

        if ($aprobacionSolicitud->estatus !== AprobacionEstatus::Pendiente) {
            return back()->withErrors(['estatus' => 'Esta aprobación ya fue procesada.']);
        }

        if (! $this->esTurno($aprobacionSolicitud)) {
            return back()->withErrors(['nivel' => 'Aún hay aprobaciones pendientes de niveles anteriores.']);
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

        // Cancelar las demas aprobaciones pendientes del mismo nivel (logica OR)
        $aprobable = $aprobacionSolicitud->aprobable;
        $aprobable->cadenaAprobacion()
            ->where('nivel', $aprobacionSolicitud->nivel)
            ->where('id', '!=', $aprobacionSolicitud->id)
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        $quedanPendientes = $aprobable->cadenaAprobacion()
            ->where('estatus', 'pendiente')
            ->exists();

        if (! $quedanPendientes && $aprobable instanceof Aprobable) {
            $aprobable->onAprobacionCompleta(auth()->id());
        }

        return back()->with('success', 'Aprobación registrada correctamente.');
    }

    public function rechazar(Request $request, Aprobacion $aprobacionSolicitud): RedirectResponse
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

        $aprobable = $aprobacionSolicitud->aprobable;
        $aprobable->cadenaAprobacion()
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        if ($aprobable instanceof Aprobable) {
            $aprobable->onAprobacionRechazada($motivo, auth()->id());
        }

        return back()->with('success', 'Solicitud rechazada.');
    }

    /**
     * Es mi turno si todos los niveles anteriores tienen al menos una aprobada.
     */
    private function esTurno(Aprobacion $aprobacion): bool
    {
        $nivelesAnteriores = Aprobacion::where('aprobable_type', $aprobacion->aprobable_type)
            ->where('aprobable_id', $aprobacion->aprobable_id)
            ->where('nivel', '<', $aprobacion->nivel)
            ->select('nivel')
            ->distinct()
            ->pluck('nivel');

        foreach ($nivelesAnteriores as $nivel) {
            $tieneAprobada = Aprobacion::where('aprobable_type', $aprobacion->aprobable_type)
                ->where('aprobable_id', $aprobacion->aprobable_id)
                ->where('nivel', $nivel)
                ->where('estatus', 'aprobada')
                ->exists();

            if (! $tieneAprobada) {
                return false;
            }
        }

        return true;
    }

    /**
     * Garantiza que las vistas heredadas (que esperan `aprobacion.solicitud`)
     * sigan funcionando aunque el modelo ya sea polimorfico.
     */
    private function withSolicitudShim(Aprobacion $a): Aprobacion
    {
        if ($a->aprobable instanceof SolicitudPago) {
            $a->setRelation('solicitud', $a->aprobable);
        }

        return $a;
    }
}
