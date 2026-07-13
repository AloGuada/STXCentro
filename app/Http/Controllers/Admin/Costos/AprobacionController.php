<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Proveedor;
use App\Models\Usuario;
use App\Services\Costos\AprobacionService;
use App\Services\Costos\RetencionCalculator;
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

        return Inertia::render('admin/costos/aprobaciones/index', [
            ...$this->construirBandeja(auth()->id()),
            'soloLectura' => false,
        ]);
    }

    /**
     * Vista supervisora (solo lectura): renderiza la misma bandeja de
     * aprobaciones tal como la ve otro aprobador, sin entrar a su cuenta y
     * sin botones de aprobar/rechazar. Incluye un selector de aprobadores.
     */
    public function bandejaDe(?Usuario $usuario = null): Response
    {
        $aprobadores = Usuario::whereIn('id', AprobacionDepartamento::query()->distinct()->pluck('aprobador_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $data = $usuario
            ? $this->construirBandeja($usuario->getKey())
            : ['pendientes' => collect(), 'aprobadas' => collect(), 'rechazadas' => collect()];

        return Inertia::render('admin/costos/aprobaciones/index', [
            ...$data,
            'soloLectura' => true,
            'aprobador' => $usuario ? ['id' => $usuario->getKey(), 'name' => $usuario->name] : null,
            'aprobadores' => $aprobadores,
        ]);
    }

    /**
     * Construye las tres colecciones (pendientes/aprobadas/rechazadas) ya
     * formadas para un aprobador. Reutilizado por la bandeja propia y por la
     * vista supervisora.
     *
     * @return array{pendientes: \Illuminate\Support\Collection, aprobadas: \Illuminate\Support\Collection, rechazadas: \Illuminate\Support\Collection}
     */
    private function construirBandeja(int|string $userId): array
    {
        $baseQuery = fn () => Aprobacion::where('aprobador_id', $userId)
            ->whereIn('aprobable_type', [SolicitudPago::class, Requisicion::class])
            ->with(['aprobable' => function ($morphTo) {
                $morphTo->morphWith([
                    SolicitudPago::class => [
                        'departamento',
                        'proveedor',
                        'solicitante',
                        'tipoSolicitud.documentos',
                        'archivos.media',
                        'media',
                        'detalles.obraRubro',
                    ],
                    Requisicion::class => [
                        'departamento',
                        'solicitante',
                        'detalles.selecciones.cotizacionPrecio',
                        'detalles.selecciones.proveedor',
                        'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial',
                    ],
                ]);
            }]);

        // Solo pendientes cuyo documento sigue esperando aprobación. Blinda la
        // bandeja contra aprobaciones que quedaron en `pendiente` aunque el
        // documento ya se canceló/rechazó/aprobó (ej. cancelaciones antiguas).
        $pendientes = $baseQuery()
            ->where('estatus', AprobacionEstatus::Pendiente->value)
            ->whereHasMorph('aprobable', [SolicitudPago::class, Requisicion::class], function ($q, string $type) {
                $q->where('estatus', $type === SolicitudPago::class
                    ? SolicitudPagoEstatus::PendienteFirma->value
                    : RequisicionEstatus::PendienteAprobacion->value);
            })
            ->latest()
            ->get()
            ->filter(fn (Aprobacion $a) => $this->esTurno($a))
            ->values();

        $aprobadas = $baseQuery()
            ->where('estatus', AprobacionEstatus::Aprobada->value)
            ->latest('fecha_respuesta')
            ->get();

        $rechazadas = $baseQuery()
            ->where('estatus', AprobacionEstatus::Rechazada->value)
            ->latest('fecha_respuesta')
            ->get();

        return [
            'pendientes' => $pendientes->map(fn (Aprobacion $a) => $this->shape($a)),
            'aprobadas' => $aprobadas->map(fn (Aprobacion $a) => $this->shape($a)),
            'rechazadas' => $rechazadas->map(fn (Aprobacion $a) => $this->shape($a)),
        ];
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

        // Las requisiciones tienen su propia vista de detalle (con cotizaciones,
        // árbol de partidas, etc). Mandamos al aprobador a esa vista; los
        // botones de Firmar/Rechazar viven en la pestaña "Aprobación" de show.
        if ($aprobable instanceof Requisicion) {
            return redirect()->route('admin.costos.requisiciones.show', $aprobable);
        }

        if ($aprobable instanceof SolicitudPago) {
            $aprobable->load([
                'solicitante',
                'departamento',
                'proveedor',
                'tipoSolicitud.documentos',
                'detalles.obraRubro.rubro',
                'detalles.obraRubro.presupuesto.presupuestable',
                'archivos.documento',
                'archivos.media',
                'aprobaciones.aprobador',
            ]);

            $aprobable->detalles->each(fn ($d) => $d->obraRubro?->presupuesto?->append('nombre_mostrar'));
        }

        return Inertia::render('admin/costos/aprobaciones/show', [
            'aprobacion' => $this->withSolicitudShim($aprobacionSolicitud),
            'solicitud' => $aprobable,
        ]);
    }

    public function aprobar(Request $request, Aprobacion $aprobacionSolicitud, AprobacionService $aprobaciones): RedirectResponse
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

        $aprobaciones->aprobar(
            $aprobacionSolicitud,
            $request->input('observaciones'),
            $request->ip(),
            gethostbyaddr($request->ip()) ?: null,
        );

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
            'fecha_respuesta' => now(),
            'observaciones' => $motivo,
            'motivo_rechazo' => $motivo,
            'ip' => $request->ip(),
            'hostname' => gethostbyaddr($request->ip()) ?: null,
        ]);
        $aprobacionSolicitud->transitionTo(AprobacionEstatus::Rechazada);

        $aprobable = $aprobacionSolicitud->aprobable;
        $aprobable->cadenaAprobacion()
            ->where('estatus', AprobacionEstatus::Pendiente->value)
            ->update([
                'estatus' => AprobacionEstatus::Cancelada->value,
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
                ->where('estatus', AprobacionEstatus::Aprobada->value)
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

    /**
     * Forma el row para la bandeja unificada: discrimina tipo y, en el caso
     * de Requisicion, calcula el total estimado a partir de las selecciones.
     */
    private function shape(Aprobacion $a): Aprobacion
    {
        $aprobable = $a->aprobable;

        // Flag unificado: ¿algún rubro tocado por los apartados/aplicados
        // activos de la entrada está EN SOBREGIRO ahora? Se calcula en vivo
        // contra el acumulado actual del rubro — así libera/cancela de otros
        // documentos del mismo rubro se reflejan sin necesidad de mantener
        // el flag estático sincronizado.
        $tieneSobregiro = $aprobable
            ? $aprobable->rubrosAfectados()
                ->whereIn('estatus', [
                    \App\Enums\Costos\RubroAfectadoEstatus::Apartado->value,
                    \App\Enums\Costos\RubroAfectadoEstatus::Aplicado->value,
                ])
                ->whereHas('obraRubro', fn ($q) => $q->whereColumn('acumulado', '>', 'presupuestado'))
                ->exists()
            : false;

        if ($aprobable instanceof SolicitudPago) {
            $aprobable->setAttribute('tiene_sobregiro', $tieneSobregiro);
            $a->setAttribute('tipo', 'solicitud_pago');
            $a->setRelation('solicitud', $aprobable);
        } elseif ($aprobable instanceof Requisicion) {
            $aprobable->append(['mejor_proveedor', 'proveedores_cotizadores_count']);
            $aprobable->setAttribute('tiene_sobregiro', $tieneSobregiro);
            $a->setAttribute('tipo', 'requisicion');
            $a->setRelation('requisicion', $aprobable);
            $a->setAttribute('requisicion_total', $this->totalNetoRequisicion($aprobable));
        }

        return $a;
    }

    /**
     * Total neto a pagar de la requisición: agrupa las selecciones por
     * (proveedor, OC), calcula IVA y retenciones por grupo con
     * {@see RetencionCalculator} y suma el neto de cada grupo. Espeja el
     * "Total neto a pagar" que se muestra en el detalle de la requisición.
     */
    private function totalNetoRequisicion(Requisicion $req): float
    {
        $calculador = new RetencionCalculator;

        /** @var array<string, array{proveedor: Proveedor, lineas: list<array{tipo_fiscal: ?string, subtotal: float}>}> $grupos */
        $grupos = [];
        foreach ($req->detalles as $detalle) {
            foreach ($detalle->selecciones as $seleccion) {
                if (! $seleccion->proveedor) {
                    continue;
                }

                $clave = $seleccion->proveedor_id.'|'.($seleccion->numero_oc ?? 1);
                $subtotal = (float) ($seleccion->cotizacionPrecio?->precio_unitario ?? 0) * (float) $seleccion->cantidad;

                $grupos[$clave]['proveedor'] ??= $seleccion->proveedor;
                $grupos[$clave]['lineas'][] = [
                    'tipo_fiscal' => $detalle->tipo_fiscal?->value,
                    'subtotal' => $subtotal,
                ];
            }
        }

        $neto = 0.0;
        foreach ($grupos as $grupo) {
            $neto += $calculador->calcular($grupo['proveedor'], $grupo['lineas'])['total_neto'];
        }

        return round($neto, 2);
    }
}
