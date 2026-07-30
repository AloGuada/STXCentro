<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Enums\ProveedorEstatus;
use App\Exceptions\Costos\OrdenCompraInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Http\Requests\Admin\Costos\FirmarRequisicionFinalRequest;
use App\Http\Requests\Admin\Costos\RequisicionLiberarRequest;
use App\Http\Requests\Admin\Costos\RequisicionStoreRequest;
use App\Http\Requests\Admin\Costos\RequisicionUpdateRequest;
use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ApprovalChainService;
use App\Services\Costos\AprobacionService;
use App\Services\Costos\BuscadorMejorProveedor;
use App\Services\Costos\ComparativoTotalesBuilder;
use App\Services\Costos\FirmasPdfBuilder;
use App\Services\Costos\OrdenCompraGenerator;
use App\Support\OrdenaColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RequisicionController extends Controller
{
    use OrdenaColumnas;

    public function __construct(private readonly ApartadoPresupuestal $apartado) {}

    public function index(Request $request): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        $query = Requisicion::query()
            ->with([
                'solicitante:id,name',
                'departamento:id,descripcion',
                'detalles:id,requisicion_id,cantidad,tipo_fiscal',
                'detalles.cotizaciones:id,requisicion_detalle_id,proveedor_id,precio_unitario',
                'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial',
                // Para el neto a pagar (cuando ya hay OC definida) — ver total_neto.
                'detalles.selecciones:id,requisicion_detalle_id,proveedor_id,numero_oc,cantidad,cotizacion_precio_id',
                'detalles.selecciones.proveedor.regimenFiscal',
                'detalles.selecciones.cotizacionPrecio:id,precio_unitario',
            ])
            // Los usuarios comunes solo ven sus requisiciones; los operadores con
            // `ver-todas` ven las de todos. Con `ver-departamentos-aprobador`, un
            // aprobador también ve las de los departamentos que aprueba. Con
            // `ver-departamento-propio`, un usuario ve lo creado por colegas de su
            // mismo departamento.
            ->unless($request->user()->can('costos.requisiciones.ver-todas'), function ($q) use ($request) {
                $user = $request->user();
                $q->where(function ($sub) use ($user) {
                    $sub->where('solicitante_id', $user->id);

                    if ($user->can('costos.requisiciones.ver-departamentos-aprobador')) {
                        $deptos = AprobacionDepartamento::departamentosDeAprobador($user->id, Requisicion::TIPO_APROBACION);
                        if ($deptos !== []) {
                            $sub->orWhereIn('departamento_id', $deptos);
                        }
                    }

                    if ($user->departamento_id && $user->can('costos.requisiciones.ver-departamento-propio')) {
                        $sub->orWhereHas('solicitante', fn ($u) => $u->where('departamento_id', $user->departamento_id));
                    }
                });
            })
            ->when($request->search, fn ($q, $s) => $q->where('folio', 'like', "%{$s}%"))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->departamento_id, fn ($q, $d) => $q->where('departamento_id', $d));

        $orden = $this->aplicarOrden($query, $request, [
            'folio' => 'folio',
            'fecha_requerida' => 'fecha_requerida',
            'estatus' => 'estatus',
            'solicitante' => fn (Builder $q, string $dir) => $q->orderBy(
                \App\Models\Usuario::select('name')->whereColumn('usuarios.id', 'costos_requisiciones.solicitante_id'), $dir),
        ], 'created_at', 'desc');

        $requisiciones = $query->paginate(15)->withQueryString();

        $mejores = app(BuscadorMejorProveedor::class)
            ->buscarLote($requisiciones->getCollection()->pluck('id')->all());

        $requisiciones->getCollection()->each(function ($r) use ($mejores) {
            $r->precargarMejorProveedor($mejores[$r->id] ?? null);
            $r->append(['mejor_proveedor', 'proveedores_cotizadores_count', 'total_neto']);
        });

        return Inertia::render('admin/costos/requisiciones/index', [
            'requisiciones' => $requisiciones,
            'filters' => $request->only('search', 'estatus', 'departamento_id'),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.requisiciones.crear');

        return Inertia::render('admin/costos/requisiciones/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'presupuestos' => $this->presupuestosOptions(),
            'obraRubros' => $this->obraRubrosOptions(),
            'usosCfdi' => $this->usosCfdiOptions(),
            'productos' => \App\Models\Costos\Producto::where('activo', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo', 'descripcion', 'unidad']),
            'usuarios' => \App\Models\Usuario::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        $requisicion = DB::transaction(function () use ($request) {
            $requisicion = Requisicion::create([
                'solicitante_id' => $request->user()->id,
                'firma_adicional_aprobador_id' => $request->input('firma_adicional_aprobador_id') ?: null,
                'departamento_id' => $request->integer('departamento_id'),
                'presupuesto_id' => $request->integer('presupuesto_id') ?: null,
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            foreach ($request->input('detalles', []) as $d) {
                $producto = $this->resolverProducto($d, $request->user()->id);

                $requisicion->detalles()->create([
                    'producto_id' => $producto->id,
                    'descripcion' => $producto->descripcion,
                    'codigo_producto' => $producto->codigo,
                    'unidad' => $producto->unidad,
                    'cantidad' => $d['cantidad'],
                    'obra_rubro_id' => $d['obra_rubro_id'],
                    'uso_cfdi_id' => $d['uso_cfdi_id'],
                    'tipo_fiscal' => $d['tipo_fiscal'] ?? 'mercancia',
                    'notas' => $d['notas'] ?? null,
                ]);
            }

            $this->marcarSobreObraCerrada($requisicion);

            return $requisicion;
        });

        foreach ($request->file('documentos', []) as $file) {
            $requisicion->media()->create([
                'descripcion' => $file->getClientOriginalName(),
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store("costos/requisiciones/{$requisicion->id}", 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición creada correctamente.');
    }

    /**
     * Resuelve el producto del catálogo de una partida: usa el `producto_id`
     * elegido o crea uno nuevo al vuelo con la descripción capturada.
     *
     * @param  array<string, mixed>  $d
     */
    private function resolverProducto(array $d, string $userId): \App\Models\Costos\Producto
    {
        if (! empty($d['producto_id'])) {
            return \App\Models\Costos\Producto::findOrFail($d['producto_id']);
        }

        return \App\Models\Costos\Producto::create([
            'descripcion' => $d['descripcion'],
            'unidad' => $d['unidad'] ?? 'pza',
            'codigo' => $d['codigo_producto'] ?? null,
            'creado_por' => $userId,
        ]);
    }

    /**
     * Duplica una requisición (en cualquier estado) en una nueva en borrador con
     * folio nuevo: copia partidas y cotizaciones (precios, proveedores, código,
     * días). NO copia selecciones, OCs ni aprobaciones. Útil para Compras para
     * re-cotizar o editar sin tocar la original.
     *
     * La copia conserva al solicitante original: Compras duplica en nombre de
     * quien pidió, no se adueña de la requisición.
     */
    public function duplicar(Requisicion $requisicion): RedirectResponse
    {
        // Duplicar es una acción de Compras (cotización), no del solicitante.
        Gate::authorize('costos.requisiciones.cotizar');

        $nueva = DB::transaction(function () use ($requisicion) {
            $nueva = Requisicion::create([
                'solicitante_id' => $requisicion->solicitante_id,
                'departamento_id' => $requisicion->departamento_id,
                'presupuesto_id' => $requisicion->presupuesto_id,
                'justificacion' => $requisicion->justificacion,
                'fecha_requerida' => $requisicion->fecha_requerida,
                'estatus' => RequisicionEstatus::Borrador->value,
            ]);

            $requisicion->load('detalles.cotizaciones');

            foreach ($requisicion->detalles as $detalle) {
                $nuevoDetalle = $nueva->detalles()->create([
                    'descripcion' => $detalle->descripcion,
                    'producto_id' => $detalle->producto_id,
                    'solo_cotizacion' => $detalle->solo_cotizacion,
                    'codigo_producto' => $detalle->codigo_producto,
                    'unidad' => $detalle->unidad,
                    'cantidad' => $detalle->cantidad,
                    'obra_rubro_id' => $detalle->obra_rubro_id,
                    'uso_cfdi_id' => $detalle->uso_cfdi_id,
                    'tipo_fiscal' => $detalle->tipo_fiscal,
                    'notas' => $detalle->notas,
                ]);

                foreach ($detalle->cotizaciones as $cotizacion) {
                    $nuevoDetalle->cotizaciones()->create([
                        'proveedor_id' => $cotizacion->proveedor_id,
                        'precio_unitario' => $cotizacion->precio_unitario,
                        'codigo_producto' => $cotizacion->codigo_producto,
                        'moneda' => $cotizacion->moneda,
                        'tiempo_entrega_dias' => $cotizacion->tiempo_entrega_dias,
                        'observaciones' => $cotizacion->observaciones,
                        'media_id' => $cotizacion->media_id,
                    ]);
                }
            }

            return $nueva;
        });

        return to_route('admin.costos.requisiciones.show', $nueva)
            ->with('success', "Requisición duplicada en {$nueva->folio} (borrador).");
    }

    public function show(Requisicion $requisicion): Response
    {
        Gate::authorize('costos.requisiciones.ver');

        // Solo ve la requisición quien puede ver todas, el solicitante, o un
        // aprobador asignado en su cadena de firmas.
        $user = auth()->user();
        abort_unless(
            $user->can('costos.requisiciones.ver-todas')
                || $requisicion->solicitante_id === $user->id
                || $requisicion->aprobaciones()->where('aprobador_id', $user->id)->exists(),
            403,
        );

        $requisicion->load([
            'solicitante:id,name',
            'controlador:id,name',
            'departamento:id,descripcion',
            'presupuesto.presupuestable',
            'detalles.obraRubro.presupuesto.presupuestable',
            'detalles.obraRubro.rubro:id,codigo,descripcion',
            'detalles.usoCfdi:id,clave,descripcion',
            'detalles.cotizaciones.proveedor:id,razon_social,nombre_comercial,estatus,activo',
            'cotizacionOpciones.proveedor:id,razon_social,nombre_comercial',
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor:id,razon_social,estatus',
            'ocs',
            'aprobaciones.aprobador:id,name',
            'ordenesGeneradas:id,folio,proveedor_id,total,estatus,requisicion_id',
            'ordenesGeneradas.proveedor:id,razon_social',
            'ordenesGeneradas.solicitudesPago:id,orden_compra_id,folio,estatus,monto_total',
            'ordenesGeneradas.entregas:id,folio,orden_compra_id,fecha_entrega',
            'media',
            'activities.causer',
        ]);

        $requisicion->presupuesto?->append(['nombre_mostrar', 'op_mostrar']);
        $requisicion->detalles->each(fn (RequisicionDetalle $d) => $d->obraRubro?->presupuesto?->append('nombre_mostrar'));

        // Último precio cotizado por cada proveedor (opción) para el insumo
        // (producto) de cada partida, en OTRAS requisiciones. Permite a compras
        // reutilizar un precio anterior con un clic. Clave: "productoId|proveedorId".
        $productoIds = $requisicion->detalles->pluck('producto_id')->filter()->unique()->values();
        $proveedorIds = $requisicion->cotizacionOpciones->pluck('proveedor_id')->unique()->values();

        $preciosPrevios = [];
        if ($productoIds->isNotEmpty() && $proveedorIds->isNotEmpty()) {
            \App\Models\Costos\RequisicionCotizacionPrecio::query()
                ->whereIn('proveedor_id', $proveedorIds)
                ->whereNotNull('precio_unitario')
                ->whereHas('detalle', fn ($q) => $q
                    ->whereIn('producto_id', $productoIds)
                    ->where('requisicion_id', '!=', $requisicion->id))
                ->with('detalle:id,producto_id')
                ->orderByDesc('id')
                ->get(['id', 'requisicion_detalle_id', 'proveedor_id', 'precio_unitario'])
                ->each(function (\App\Models\Costos\RequisicionCotizacionPrecio $p) use (&$preciosPrevios) {
                    $productoId = $p->detalle?->producto_id;
                    if (! $productoId) {
                        return;
                    }
                    $preciosPrevios[$productoId.'|'.$p->proveedor_id] ??= (float) $p->precio_unitario;
                });
        }

        $aprobacionPendienteId = $this->aprobacionPendienteParaUsuario($requisicion);
        $esUltimoNivel = $this->esUltimoNivel($requisicion, $aprobacionPendienteId);

        return Inertia::render('admin/costos/requisiciones/show', [
            'requisicion' => $requisicion,
            'preciosPrevios' => $preciosPrevios,
            'proveedores' => Proveedor::whereIn('estatus', [ProveedorEstatus::PendienteValidacion->value, ProveedorEstatus::Activo->value])
                ->with('regimenFiscal:id,clave')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial', 'maneja_credito', 'estatus', 'tipo_persona', 'regimen_fiscal_id']),
            'obraRubros' => $this->obraRubrosOptions(),
            'usosCfdi' => $this->usosCfdiOptions(),
            'aprobacionPendienteId' => $aprobacionPendienteId,
            'esUltimoNivel' => $esUltimoNivel,
            'proveedoresPorValidar' => $esUltimoNivel ? $this->proveedoresPorValidar($requisicion) : [],
        ]);
    }

    /**
     * Formato comparativo de la requisición en PDF. Mismo render que el que se
     * obtiene desde la OC, pero accesible directamente desde la requisición.
     */
    public function pdf(Requisicion $requisicion): HttpResponse
    {
        Gate::authorize('costos.requisiciones.ver');

        abort_unless(
            in_array($requisicion->estatus, [
                RequisicionEstatus::PendienteAprobacionInterna,
                RequisicionEstatus::AprobadaInterna,
                RequisicionEstatus::PendienteAprobacion,
                RequisicionEstatus::Aprobada,
                RequisicionEstatus::Liberada,
            ], true),
            403,
            'El comparativo solo puede generarse una vez enviada a aprobación.'
        );

        $requisicion->load([
            'solicitante',
            'departamento',
            'detalles.cotizaciones.opcion',
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor:id,razon_social,tipo_persona,regimen_fiscal_id',
            'detalles.selecciones.proveedor.regimenFiscal:id,clave',
            'detalles.obraRubro.obra:id,no,descripcion',
            'detalles.obraRubro.rubro:id,codigo,descripcion',
            'cotizacionOpciones.proveedor:id,razon_social,nombre_comercial',
        ]);

        // Las partidas "solo cotización" (ej. fletes de cantidad variable) sí se
        // muestran en el comparativo como referencia; el builder de totales las
        // excluye de la suma y la vista las marca.
        $firmas = app(FirmasPdfBuilder::class)->build(
            $requisicion->tipoAprobacion(),
            $requisicion->departamento_id,
            $requisicion->aprobaciones()->with('aprobador')->get(),
        );

        return Pdf::loadView('pdf.costos.formato-requisicion-comparativo', [
            'requisicion' => $requisicion,
            'firmas' => $firmas,
            'totales' => app(ComparativoTotalesBuilder::class)->build($requisicion),
        ])->setPaper('letter', 'landscape')->stream("Comparativo-{$requisicion->folio}.pdf");
    }

    /**
     * ¿La aprobación pendiente del usuario es el último nivel? Lo es cuando no
     * existe ninguna aprobación pendiente en un nivel superior.
     */
    private function esUltimoNivel(Requisicion $requisicion, ?int $aprobacionPendienteId): bool
    {
        if (! $aprobacionPendienteId) {
            return false;
        }

        $mia = $requisicion->aprobaciones->firstWhere('id', $aprobacionPendienteId);
        if (! $mia) {
            return false;
        }

        return $requisicion->aprobaciones
            ->where('nivel', '>', $mia->nivel)
            ->where('estatus', 'pendiente')
            ->isEmpty();
    }

    /**
     * Proveedores referenciados por las selecciones que aún no están activos y
     * por tanto requieren validación documental antes de aprobar. Incluye sus
     * documentos, las partidas que ganaron y las cotizaciones alternativas
     * (otros proveedores que cotizaron esas mismas partidas) para reasignar.
     *
     * @return list<array<string, mixed>>
     */
    private function proveedoresPorValidar(Requisicion $requisicion): array
    {
        $selecciones = $requisicion->detalles->flatMap->selecciones;
        $provIds = $selecciones->pluck('proveedor_id')->unique()->filter()->all();

        if (empty($provIds)) {
            return [];
        }

        $proveedores = Proveedor::with(['regimenFiscal:id,clave,descripcion', 'media', 'banco:id,nombre'])
            ->whereIn('id', $provIds)
            ->where('estatus', '!=', ProveedorEstatus::Activo->value)
            ->get();

        return $proveedores->map(function (Proveedor $prov) use ($requisicion, $selecciones) {
            $detalleIds = $selecciones->where('proveedor_id', $prov->id)
                ->pluck('requisicion_detalle_id')->unique();

            $partidas = $requisicion->detalles
                ->whereIn('id', $detalleIds)
                ->map(function (RequisicionDetalle $d) use ($prov) {
                    $alternativas = $d->cotizaciones
                        ->where('proveedor_id', '!=', $prov->id)
                        ->filter(fn ($c) => $c->proveedor && $c->proveedor->estatus === ProveedorEstatus::Activo)
                        ->map(fn ($c) => [
                            'cotizacion_precio_id' => $c->id,
                            'proveedor_id' => $c->proveedor_id,
                            'proveedor' => $c->proveedor?->razon_social,
                            'precio_unitario' => (float) $c->precio_unitario,
                            'moneda' => $c->moneda ?? 'mxn',
                        ])->values();

                    return [
                        'requisicion_detalle_id' => $d->id,
                        'descripcion' => $d->descripcion,
                        'alternativas' => $alternativas,
                    ];
                })->values();

            $url = fn (?string $desc) => ($m = $prov->media->firstWhere('descripcion', $desc))
                ? Storage::disk('public')->url($m->path)
                : null;

            return [
                'id' => $prov->id,
                'razon_social' => $prov->razon_social,
                'rfc' => $prov->rfc,
                'tipo_persona' => $prov->tipo_persona,
                'tipo_proveedor' => $prov->tipo_proveedor?->value,
                'forma_pago' => $prov->forma_pago?->value,
                'regimen' => $prov->regimenFiscal?->descripcion,
                'banco' => $prov->banco?->nombre ?? $prov->banco_nombre,
                'titular_cuenta' => $prov->titular_cuenta,
                'numero_cuenta' => $prov->numero_cuenta,
                'clabe' => $prov->clabe,
                'tarjeta' => $prov->tarjeta,
                'moneda_cuenta' => $prov->moneda_cuenta,
                'constancia_url' => $url('constancia_fiscal'),
                'caratula_url' => $url('caratula_bancaria'),
                'partidas' => $partidas,
            ];
        })->values()->all();
    }

    /**
     * Firma del último nivel con validación documental de proveedores. Por cada
     * proveedor pendiente: se activa, o se rechaza reasignando sus partidas a un
     * proveedor que ya cotizó. Si algún proveedor queda rechazado sin reemplazo
     * (o queda alguna selección apuntando a proveedor no activo), se rechaza la
     * requisición. En caso contrario se firma y la requisición pasa a aprobada.
     */
    public function firmarFinal(FirmarRequisicionFinalRequest $request, Requisicion $requisicion, AprobacionService $aprobaciones): RedirectResponse
    {
        $requisicion->load([
            'aprobaciones',
            'detalles.selecciones.proveedor:id,estatus',
            'detalles.cotizaciones',
        ]);

        $aprobacionPendienteId = $this->aprobacionPendienteParaUsuario($requisicion);
        if (! $aprobacionPendienteId) {
            return back()->withErrors(['nivel' => 'No tiene una firma pendiente en turno para esta requisición.']);
        }

        if (! $this->esUltimoNivel($requisicion, $aprobacionPendienteId)) {
            return back()->withErrors(['nivel' => 'La validación de proveedores solo se realiza en el último nivel.']);
        }

        /** @var Aprobacion $aprobacion */
        $aprobacion = $requisicion->aprobaciones->firstWhere('id', $aprobacionPendienteId);

        // Resolución DNS inversa fuera de la transacción (es una llamada de red
        // bloqueante; no debe mantener la transacción abierta).
        $hostname = gethostbyaddr($request->ip()) ?: null;

        DB::transaction(function () use ($request, $requisicion, $aprobacion, $aprobaciones, $hostname) {
            $rechazoSinReemplazo = false;

            foreach ($request->input('validaciones', []) as $val) {
                /** @var Proveedor $proveedor */
                $proveedor = Proveedor::findOrFail($val['proveedor_id']);

                $proveedor->update([
                    'estatus' => $val['accion'] === 'activar' ? ProveedorEstatus::Activo : ProveedorEstatus::Rechazado,
                    'activo' => $val['accion'] === 'activar',
                    'validado_por' => auth()->id(),
                    'validado_at' => now(),
                    'observacion_validacion' => $val['observacion'] ?? null,
                ]);

                if ($val['accion'] === 'activar') {
                    continue;
                }

                $reemplazos = $val['reemplazos'] ?? [];
                if (empty($reemplazos)) {
                    $rechazoSinReemplazo = true;

                    continue;
                }

                foreach ($reemplazos as $r) {
                    $cotizacion = RequisicionCotizacionPrecio::where('id', $r['cotizacion_precio_id'])
                        ->where('requisicion_detalle_id', $r['requisicion_detalle_id'])
                        ->where('proveedor_id', $r['nuevo_proveedor_id'])
                        ->firstOrFail();

                    RequisicionSeleccion::where('requisicion_detalle_id', $r['requisicion_detalle_id'])
                        ->where('proveedor_id', $proveedor->id)
                        ->update([
                            'proveedor_id' => $cotizacion->proveedor_id,
                            'cotizacion_precio_id' => $cotizacion->id,
                        ]);
                }
            }

            // Tras reasignar, ninguna selección puede apuntar a un proveedor no activo.
            $requisicion->load('detalles.selecciones.proveedor:id,estatus');
            $hayInvalidas = $requisicion->detalles->flatMap->selecciones
                ->contains(fn (RequisicionSeleccion $s) => $s->proveedor?->estatus !== ProveedorEstatus::Activo);

            if ($rechazoSinReemplazo || $hayInvalidas) {
                $motivo = $request->input('observaciones');
                $aprobacion->update([
                    'fecha_respuesta' => now(),
                    'observaciones' => $motivo,
                    'motivo_rechazo' => $motivo,
                    'ip' => $request->ip(),
                    'hostname' => $hostname,
                ]);
                $aprobacion->transitionTo(AprobacionEstatus::Rechazada);
                $requisicion->cadenaAprobacion()
                    ->where('estatus', 'pendiente')
                    ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);
                $requisicion->onAprobacionRechazada($motivo, auth()->id());

                return;
            }

            $aprobaciones->aprobar(
                $aprobacion,
                $request->input('observaciones'),
                $request->ip(),
                $hostname,
            );
        });

        return back()->with('success', 'Requisición firmada.');
    }

    /**
     * Devuelve el id de la aprobación pendiente del usuario actual cuando es
     * su turno en la cadena de firmas. Si no es su turno o no tiene firma
     * asignada, devuelve null.
     */
    private function aprobacionPendienteParaUsuario(Requisicion $requisicion): ?int
    {
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        $mias = $requisicion->aprobaciones
            ->where('aprobador_id', $userId)
            ->where('estatus', 'pendiente');

        foreach ($mias as $aprobacion) {
            $nivelesAnteriores = $requisicion->aprobaciones
                ->where('nivel', '<', $aprobacion->nivel)
                ->pluck('nivel')
                ->unique();

            $todosAprobados = $nivelesAnteriores->every(
                fn ($nivel) => $requisicion->aprobaciones
                    ->where('nivel', $nivel)
                    ->where('estatus', 'aprobada')
                    ->isNotEmpty()
            );

            if ($todosAprobados) {
                return $aprobacion->id;
            }
        }

        return null;
    }

    public function edit(Requisicion $requisicion): Response
    {
        Gate::authorize('costos.requisiciones.crear');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::Borrador, RequisicionEstatus::Rechazada], true)) {
            abort(403, 'Solo se pueden editar requisiciones en borrador o rechazadas.');
        }

        $requisicion->lock(auth()->id());
        $requisicion->load(['detalles', 'departamento']);

        return Inertia::render('admin/costos/requisiciones/edit', [
            'requisicion' => $requisicion,
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'presupuestos' => $this->presupuestosOptions(),
            'obraRubros' => $this->obraRubrosOptions(),
            'usosCfdi' => $this->usosCfdiOptions(),
        ]);
    }

    public function update(RequisicionUpdateRequest $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.crear');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::Borrador, RequisicionEstatus::Rechazada], true)) {
            return back()->withErrors(['estatus' => 'Solo se pueden editar requisiciones en borrador o rechazadas.']);
        }

        $requisicion->assertVersion($request->input('_version'));

        DB::transaction(function () use ($request, $requisicion) {
            $requisicion->update([
                'departamento_id' => $request->integer('departamento_id'),
                'presupuesto_id' => $request->integer('presupuesto_id') ?: null,
                'justificacion' => $request->input('justificacion'),
                'fecha_requerida' => $request->input('fecha_requerida'),
                // Editar la requisición invalida una aprobación interna previa.
                'control_por' => null,
                'control_at' => null,
            ]);

            $idsKeep = collect($request->input('detalles', []))
                ->pluck('id')
                ->filter()
                ->all();

            $requisicion->detalles()
                ->whereNotIn('id', $idsKeep)
                ->delete();

            foreach ($request->input('detalles', []) as $d) {
                if (! empty($d['id'])) {
                    // codigo_producto y tipo_fiscal se gestionan en el tab de
                    // cotización (Compras), no en la edición de la requisición:
                    // no se tocan aquí para no pisar lo capturado.
                    RequisicionDetalle::where('id', $d['id'])
                        ->where('requisicion_id', $requisicion->id)
                        ->update([
                            'descripcion' => $d['descripcion'],
                            'unidad' => $d['unidad'] ?? 'pza',
                            'cantidad' => $d['cantidad'],
                            'obra_rubro_id' => $d['obra_rubro_id'],
                            'uso_cfdi_id' => $d['uso_cfdi_id'],
                            'notas' => $d['notas'] ?? null,
                        ]);
                } else {
                    $requisicion->detalles()->create([
                        'descripcion' => $d['descripcion'],
                        'codigo_producto' => $d['codigo_producto'] ?? null,
                        'unidad' => $d['unidad'] ?? 'pza',
                        'cantidad' => $d['cantidad'],
                        'obra_rubro_id' => $d['obra_rubro_id'],
                        'uso_cfdi_id' => $d['uso_cfdi_id'],
                        'tipo_fiscal' => $d['tipo_fiscal'] ?? 'mercancia',
                        'notas' => $d['notas'] ?? null,
                    ]);
                }
            }

            $this->marcarSobreObraCerrada($requisicion);

            // Si venia de rechazada, vuelve a borrador para empezar nueva ronda.
            if ($requisicion->estatus === RequisicionEstatus::Rechazada) {
                $requisicion->transitionTo(RequisicionEstatus::Borrador);
            }
        });

        $requisicion->unlock($request->user()->id);

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición actualizada.');
    }

    public function cancelar(CancelarRequest $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cancelar');

        if (in_array($requisicion->estatus, [RequisicionEstatus::Liberada, RequisicionEstatus::Cancelada], true)) {
            return back()->withErrors(['estatus' => 'No se puede cancelar una requisición en este estado.']);
        }

        DB::transaction(function () use ($request, $requisicion) {
            $requisicion->cadenaAprobacion()
                ->where('estatus', 'pendiente')
                ->update(['estatus' => 'cancelada', 'fecha_respuesta' => now()]);

            app(\App\Services\Costos\ApartadoPresupuestal::class)
                ->cancelarApartadosDe($requisicion, 'requisición cancelada');

            $requisicion->transitionTo(RequisicionEstatus::Cancelada);
            $requisicion->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

        return back()->with('success', 'Requisición cancelada.');
    }

    /**
     * Envia la requisicion a la cadena de firmas. Solo permitido en `cotizada`
     * y exige captura completa: rubro por detalle, modo de pago, partidas
     * cubiertas 100% por selecciones, y cada seleccion con precio capturado.
     */
    /**
     * Botón 2 (gerente): da la aprobación interna sobre una requisición que está
     * en la bandeja interna. Transiciona `pendiente_aprobacion_interno →
     * aprobada_interna` y guarda quién/cuándo. La etapa interna ES este control
     * gerencial (no hay cadena de firmas todavía).
     */
    public function aprobarInterno(Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.control');

        if ($requisicion->estatus !== RequisicionEstatus::PendienteAprobacionInterna) {
            return back()->withErrors(['control' => 'La aprobación interna solo aplica a requisiciones pendientes de aprobación interna.']);
        }

        // Mismas validaciones que mandar a aprobación: no se da la aprobación
        // interna si la cotización está incompleta.
        if ($errores = $this->validarCotizacionCompleta($requisicion)) {
            return back()->withErrors($errores);
        }

        $requisicion->update([
            'control_por' => auth()->id(),
            'control_at' => now(),
        ]);
        $requisicion->transitionTo(RequisicionEstatus::AprobadaInterna);

        return back()->with('success', 'Aprobación interna registrada.');
    }

    /**
     * Botón 3 (gerente): rechaza la aprobación interna y regresa la requisición a
     * borrador para re-cotizar. Solo antes de mandarse a firmas.
     */
    public function rechazarInterno(Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.control');

        if ($requisicion->estatus !== RequisicionEstatus::PendienteAprobacionInterna) {
            return back()->withErrors(['control' => 'Solo se puede rechazar una requisición pendiente de aprobación interna.']);
        }

        $requisicion->update([
            'control_por' => null,
            'control_at' => null,
        ]);
        $requisicion->transitionTo(RequisicionEstatus::Borrador);

        return back()->with('success', 'Requisición regresada a borrador para re-cotizar.');
    }

    /**
     * Botón 1 (auxiliar): mueve la requisición de borrador a la bandeja del
     * gerente (pendiente de aprobación). NO arranca la cadena de firmas: eso
     * ocurre en `iniciarAprobacion` (botón 3), una vez que el gerente dio su
     * aprobación gerencial.
     */
    public function enviarAprobacion(Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if ($requisicion->estatus !== RequisicionEstatus::Borrador) {
            return back()->withErrors(['estatus' => 'La requisición debe estar en borrador para enviarse a aprobación.']);
        }

        if ($errores = $this->validarCotizacionCompleta($requisicion)) {
            return back()->withErrors($errores);
        }

        $requisicion->transitionTo(RequisicionEstatus::PendienteAprobacionInterna);

        return back()->with('success', 'Requisición enviada a aprobación interna.');
    }

    /**
     * Botón "Mandar a aprobación" (auxiliar): con la aprobación interna ya dada,
     * mueve la requisición a la aprobación formal. Aparta el presupuesto (5 días),
     * transiciona `aprobada_interna → pendiente_aprobacion` y crea la cadena de
     * firmas. Permanece en pendiente de aprobación hasta que se firmen todos los
     * niveles (o se aprueba sola si todos se saltan).
     */
    public function iniciarAprobacion(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if ($requisicion->estatus !== RequisicionEstatus::AprobadaInterna) {
            return back()->withErrors(['estatus' => 'La requisición debe tener la aprobación interna antes de mandarse a firmas.']);
        }

        if ($requisicion->cadenaAprobacion()->exists()) {
            return back()->withErrors(['estatus' => 'La requisición ya se mandó a firmas.']);
        }

        if ($errores = $this->validarCotizacionCompleta($requisicion)) {
            return back()->withErrors($errores);
        }

        DB::transaction(function () use ($request, $requisicion) {
            // Apartado temporal de presupuesto (5 días): cada partida usa
            // el monto de sus selecciones (∑ cantidad × precio cotizado). Se
            // hace ANTES de armar la cadena porque el salto de niveles depende
            // de que ya exista presupuesto reservado.
            $items = $requisicion->detalles
                ->filter(fn ($d) => $d->obra_rubro_id)
                ->map(fn ($d) => [
                    'obra_rubro_id' => (int) $d->obra_rubro_id,
                    'monto' => (float) $d->selecciones->sum(
                        fn ($s) => (float) $s->cantidad * (float) ($s->cotizacionPrecio?->precio_unitario ?? 0)
                    ),
                    'descripcion' => $d->descripcion,
                    'moneda' => $d->selecciones->first()?->cotizacionPrecio?->moneda ?? 'mxn',
                    'tipo_cambio' => $requisicion->tipo_cambio ? (float) $requisicion->tipo_cambio : null,
                ])
                ->filter(fn ($i) => $i['monto'] > 0);

            app(ApartadoPresupuestal::class)
                ->apartarDocumento($requisicion, $items, $request->user()->id);

            $requisicion->transitionTo(RequisicionEstatus::PendienteAprobacion);

            $chain = app(ApprovalChainService::class);
            $creados = $chain->crearCadenaAprobaciones($requisicion);

            // Si había niveles configurados pero todos se saltaron (presupuesto
            // reservado + niveles marcados), la requisición se aprueba sola.
            if ($creados === 0 && $chain->tieneCadenaConfigurada($requisicion)) {
                $requisicion->onAprobacionCompleta($request->user()->id);
            }
        });

        return back()->with('success', 'Requisición mandada a aprobación.');
    }

    /**
     * Guarda el tipo de cambio de la requisición (una vez, a nivel documento).
     * Se usa para convertir a MXN el apartado y la afectación de sus OC cuando
     * las cotizaciones son en divisa. Editable mientras no se haya liberado.
     */
    public function guardarTipoCambio(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $data = $request->validate([
            'tipo_cambio' => ['required', 'numeric', 'min:0.000001'],
        ]);

        if (in_array($requisicion->estatus, [RequisicionEstatus::Liberada, RequisicionEstatus::Cancelada], true)) {
            return back()->withErrors(['tipo_cambio' => 'La requisición ya no admite cambios de tipo de cambio.']);
        }

        $requisicion->update(['tipo_cambio' => $data['tipo_cambio']]);

        return back()->with('success', 'Tipo de cambio guardado.');
    }

    /**
     * Valida que la cotización esté completa para avanzar (verificación
     * gerencial y envío a aprobación): mínimo de proveedores comparados, uso
     * de CFDI y centro de costos por partida, partidas cubiertas 100% por
     * selecciones con precio, y al menos una OC definida. Devuelve los errores
     * (clave => mensaje) o null si todo está correcto.
     *
     * @return array<string, string>|null
     */
    private function validarCotizacionCompleta(Requisicion $requisicion): ?array
    {
        $requisicion->loadMissing(['detalles.selecciones.cotizacionPrecio', 'detalles.cotizaciones']);

        // En modo "dedazo" basta un proveedor (no hay comparativa). Fuera de él,
        // se exige el mínimo configurado (por defecto 3).
        $minEmpresas = $requisicion->modo_dedazo ? 1 : (int) config('costos.min_empresas_cotizacion', 3);

        // La cotización debe comparar al menos N proveedores en total (no por
        // partida): basta con tener N empresas distintas en toda la requisición.
        // Las partidas "solo cotización" no cuentan: no se adjudican a proveedor.
        $empresasTotal = $requisicion->detalles
            ->reject->solo_cotizacion
            ->flatMap->cotizaciones
            ->pluck('proveedor_id')
            ->unique()
            ->count();

        if ($empresasTotal < $minEmpresas) {
            return ['cotizaciones' => "La cotización debe comparar al menos {$minEmpresas} proveedores (tiene {$empresasTotal})."];
        }

        foreach ($requisicion->detalles as $detalle) {
            // Las partidas "solo cotización" son de referencia: no requieren
            // uso de CFDI, centro de costos ni selección de proveedor.
            if ($detalle->solo_cotizacion) {
                continue;
            }

            if (empty($detalle->uso_cfdi_id)) {
                return ['detalles' => "La partida \"{$detalle->descripcion}\" no tiene uso de CFDI asignado."];
            }

            if (empty($detalle->obra_rubro_id)) {
                return ['detalles' => "La partida \"{$detalle->descripcion}\" no tiene centro de costos asignado."];
            }

            $sumaSelecciones = (float) $detalle->selecciones->sum('cantidad');
            $cantidadPartida = (float) $detalle->cantidad;

            if ($sumaSelecciones <= 0.0) {
                return ['selecciones' => "La partida \"{$detalle->descripcion}\" no tiene proveedor asignado."];
            }

            if ($sumaSelecciones + config('costos.epsilon_cantidad') < $cantidadPartida) {
                return ['selecciones' => "La partida \"{$detalle->descripcion}\" no está cubierta al 100% por las selecciones."];
            }

            if ($sumaSelecciones > $cantidadPartida + config('costos.epsilon_cantidad')) {
                return ['selecciones' => "La partida \"{$detalle->descripcion}\" tiene selecciones por encima de la cantidad solicitada."];
            }

            foreach ($detalle->selecciones as $sel) {
                if (! $sel->cotizacionPrecio || ! $sel->cotizacionPrecio->precio_unitario) {
                    return ['selecciones' => "La partida \"{$detalle->descripcion}\" tiene una selección sin precio cotizado."];
                }
            }
        }

        if ($requisicion->ocs()->doesntExist()) {
            return ['ocs' => 'Debes definir al menos una orden de compra antes de enviar a aprobación.'];
        }

        return null;
    }

    /**
     * Compras libera la requisicion aprobada: agrupa selecciones por
     * (proveedor, numero_oc), crea N OCs con su rubro heredado del detalle.
     * El payload `ocs[]` define modo_pago, notas y fecha por OC.
     * `total` = subtotal_lineas + IVA(16%). Aplica impacto
     * presupuestal en la misma transaccion. Idempotente: si ya hay OCs,
     * bloquea.
     */
    public function liberar(RequisicionLiberarRequest $request, Requisicion $requisicion, OrdenCompraGenerator $generator): RedirectResponse
    {
        if ($requisicion->estatus !== RequisicionEstatus::Aprobada) {
            return back()->withErrors(['estatus' => 'Solo requisiciones aprobadas se pueden liberar.']);
        }

        if ($requisicion->ordenesGeneradas()->exists()) {
            return back()->withErrors(['estatus' => 'Esta requisición ya tiene OCs generadas.']);
        }

        // Si la requisición se aprobó saltando niveles (presupuesto reservado) y
        // su apartado ya venció, el salto dejó de ser válido: se bloquea liberar
        // hasta re-apartar (que revalida el presupuesto y expone el sobregiro).
        $chain = app(ApprovalChainService::class);
        if ($chain->huboNivelesSaltados($requisicion) && ! $requisicion->tienePresupuestoReservado()) {
            return back()->withErrors([
                'estatus' => 'El apartado de presupuesto venció y esta requisición se aprobó saltando niveles. Re-aparta el presupuesto (verifica que no haya sobregiro) antes de liberar.',
            ]);
        }

        try {
            $this->generarOrdenesCompra($requisicion, $generator, $request->user()->id);
        } catch (OrdenCompraInvalidaException $e) {
            return back()->withErrors($e->errores);
        }

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición liberada y órdenes de compra generadas.');
    }

    /**
     * Modo "dedazo": tras la verificación gerencial (mismo punto de control),
     * convierte una requisición cotizada DIRECTO a orden de compra, sin cadena
     * de aprobación ni apartado temporal. El único requisito flexibilizado es el
     * mínimo de proveedores (basta 1); todo lo demás (uso CFDI, centro de costos,
     * selecciones 100%, OC definida) se mantiene.
     */
    public function convertirAOc(Requisicion $requisicion, OrdenCompraGenerator $generator): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.liberar');

        if (! $requisicion->modo_dedazo) {
            return back()->withErrors(['modo_dedazo' => 'Esta acción solo aplica a requisiciones en modo dedazo.']);
        }

        if ($requisicion->estatus !== RequisicionEstatus::AprobadaInterna) {
            return back()->withErrors(['estatus' => 'La requisición debe tener la aprobación interna antes de convertirse a OC.']);
        }

        if ($errores = $this->validarCotizacionCompleta($requisicion)) {
            return back()->withErrors($errores);
        }

        try {
            DB::transaction(function () use ($requisicion, $generator): void {
                // Sin cadena de aprobación ni apartado: se lleva directo a
                // Aprobada y el generador la deja en Liberada, aplicando el
                // impacto presupuestal permanente (Aplicado).
                $requisicion->transitionTo(RequisicionEstatus::Aprobada);

                $this->generarOrdenesCompra($requisicion, $generator, auth()->id());
            });
        } catch (OrdenCompraInvalidaException $e) {
            return back()->withErrors($e->errores);
        }

        return to_route('admin.costos.requisiciones.show', $requisicion)
            ->with('success', 'Requisición convertida a orden de compra (dedazo).');
    }

    /**
     * Activa/desactiva el modo "dedazo" mientras la requisición está en captura
     * o cotización.
     */
    public function setDedazo(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::Borrador, RequisicionEstatus::Rechazada], true)) {
            return back()->withErrors(['modo_dedazo' => 'Solo se puede cambiar el modo mientras la requisición está en captura o cotización.']);
        }

        $requisicion->update(['modo_dedazo' => $request->boolean('modo_dedazo')]);

        return back()->with('success', $requisicion->modo_dedazo ? 'Modo dedazo activado.' : 'Modo dedazo desactivado.');
    }

    /**
     * Genera las OCs de la requisición (agrupando selecciones por proveedor/OC)
     * y aplica el impacto presupuestal. Compartido por `liberar` (flujo normal)
     * y `convertirAOc` (dedazo). Lanza OrdenCompraInvalidaException si algún dato
     * impide generar.
     */
    private function generarOrdenesCompra(Requisicion $requisicion, OrdenCompraGenerator $generator, string $userId): void
    {
        $requisicion->load([
            'detalles.selecciones.cotizacionPrecio',
            'detalles.selecciones.proveedor',
            'ocs',
        ]);

        foreach ($requisicion->detalles as $detalle) {
            if ($detalle->solo_cotizacion) {
                continue;
            }

            if (empty($detalle->uso_cfdi_id)) {
                throw new OrdenCompraInvalidaException(['detalles' => "La partida \"{$detalle->descripcion}\" no tiene uso de CFDI asignado."]);
            }

            if (empty($detalle->obra_rubro_id)) {
                throw new OrdenCompraInvalidaException(['detalles' => "La partida \"{$detalle->descripcion}\" no tiene centro de costos asignado."]);
            }
        }

        // Las partidas "solo cotización" no se adjudican; se excluyen de las OCs.
        $todasSelecciones = $requisicion->detalles->reject->solo_cotizacion->flatMap->selecciones;

        // Los metadatos de cada OC (modo_pago, fecha, notas) se persistieron en
        // el tab "Definir OC" (costos_requisicion_ocs). Se indexan por
        // (proveedor_id, numero_oc) para que el generador los resuelva por OC.
        $ocsPayload = $requisicion->ocs
            ->keyBy(fn ($oc) => $oc->proveedor_id.'|'.$oc->numero_oc)
            ->map(fn ($oc) => [
                'proveedor_id' => $oc->proveedor_id,
                'numero_oc' => $oc->numero_oc,
                'modo_pago' => $oc->modo_pago->value,
                'metodo_pago' => $oc->metodo_pago,
                'fecha_entrega' => $oc->fecha_entrega?->format('Y-m-d'),
                'fecha_pago' => $oc->fecha_pago?->format('Y-m-d'),
                'notas' => $oc->notas,
                'pagos' => $oc->pagos ?? [],
            ]);

        $grupos = $todasSelecciones->groupBy(
            fn (RequisicionSeleccion $s) => $s->proveedor_id.'|'.((int) ($s->numero_oc ?: 1))
        );

        foreach ($grupos as $key => $selecciones) {
            // Si faltara el metadato (no debería: las selecciones lo siembran),
            // se aplica un default seguro en vez de bloquear la liberación.
            if (! $ocsPayload->has($key)) {
                $primera = $selecciones->first();
                $ocsPayload[$key] = [
                    'proveedor_id' => $primera->proveedor_id,
                    'numero_oc' => (int) ($primera->numero_oc ?: 1),
                    'modo_pago' => $primera->proveedor?->maneja_credito ? 'credito' : 'contado',
                    'metodo_pago' => 'transferencia',
                    'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
                    'fecha_pago' => null,
                    'notas' => null,
                    'pagos' => [],
                ];
            }

            // Una OC no puede mezclar monedas: todas sus cotizaciones deben coincidir.
            $monedas = $selecciones
                ->map(fn (RequisicionSeleccion $s) => $s->cotizacionPrecio?->moneda ?? 'mxn')
                ->unique();

            if ($monedas->count() > 1) {
                throw new OrdenCompraInvalidaException(['ocs' => "La OC del grupo {$key} mezcla monedas (".$monedas->implode(', ').'). Separa las partidas por moneda en OCs distintas.']);
            }

            $proveedor = $selecciones->first()?->proveedor;
            if ($proveedor?->bloqueadoPorComplemento()) {
                throw new OrdenCompraInvalidaException(['ocs' => "El proveedor \"{$proveedor->razon_social}\" está bloqueado por un complemento de pago pendiente. No se puede liberar la OC hasta regularizar."]);
            }
        }

        $generator->generar($requisicion, $grupos, $ocsPayload, $userId);
    }

    /**
     * Re-aparta el presupuesto de una requisición cuyos apartados vencieron.
     * Solo aplica antes de liberar la requisición (estados PendienteAprobacion
     * o Aprobada). Si todavía tiene apartados vigentes, no hace nada.
     */
    public function reApartar(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        if (! in_array($requisicion->estatus, [RequisicionEstatus::PendienteAprobacion, RequisicionEstatus::Aprobada], true)) {
            return back()->withErrors(['estatus' => 'Solo se puede re-apartar una requisición pendiente o aprobada, antes de liberarse.']);
        }

        $requisicion->load('detalles.selecciones.cotizacionPrecio');

        $items = $requisicion->detalles
            ->filter(fn ($d) => $d->obra_rubro_id)
            ->map(fn ($d) => [
                'obra_rubro_id' => (int) $d->obra_rubro_id,
                'monto' => (float) $d->selecciones->sum(
                    fn ($s) => (float) $s->cantidad * (float) ($s->cotizacionPrecio?->precio_unitario ?? 0)
                ),
                'descripcion' => $d->descripcion,
                'moneda' => $d->selecciones->first()?->cotizacionPrecio?->moneda ?? 'mxn',
                'tipo_cambio' => $requisicion->tipo_cambio ? (float) $requisicion->tipo_cambio : null,
            ])
            ->filter(fn ($i) => $i['monto'] > 0);

        $this->apartado->reApartarDocumento($requisicion, $items, $request->user()->id);

        return back()->with('success', 'Presupuesto re-apartado por 5 días.');
    }

    /**
     * Lista de obra-rubros con info presupuestal para selectores.
     * `disponible` = presupuestado - ejercido (acumulado) - apartado;
     * `sobregiro` cuando es negativo.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string, presupuestado: float, acumulado: float, apartado: float, comprometido: float, disponible: float, sobregiro: bool}>
     */
    /**
     * Marca si la requisición carga a algún centro de costos cuyo objetivo
     * (obra base o adicional) está cerrado. El aprobador lo verá señalado;
     * no altera la cadena de aprobaciones.
     */
    private function marcarSobreObraCerrada(Requisicion $requisicion): void
    {
        $rubroIds = $requisicion->detalles()->pluck('obra_rubro_id')->filter()->unique();

        $cerrada = ObraRubro::with(['presupuesto:id,estatus'])
            ->whereIn('id', $rubroIds)
            ->get()
            ->contains(fn (ObraRubro $or) => $or->estaCerrado());

        $requisicion->update(['sobre_obra_cerrada' => $cerrada]);
    }

    private function obraRubrosOptions(): \Illuminate\Support\Collection
    {
        return ObraRubro::with([
            'presupuesto.presupuestable',
            'rubro:id,codigo,descripcion',
        ])
            ->get()
            ->map(function (ObraRubro $or) {
                $disponible = $or->disponible;
                $presupuestoLabel = $or->presupuesto?->nombreMostrar() ?? '-';

                return [
                    'id' => $or->id,
                    'presupuesto_id' => $or->presupuesto_id,
                    'presupuesto_label' => $presupuestoLabel,
                    'rubro_label' => trim(sprintf('%s %s', $or->rubro?->codigo ?? '', $or->rubro?->descripcion ?? '-')),
                    'label' => trim(sprintf(
                        '%s · %s %s',
                        $presupuestoLabel,
                        $or->rubro?->codigo ?? '',
                        $or->rubro?->descripcion ?? '-',
                    )),
                    'presupuestado' => (float) $or->presupuestado,
                    'acumulado' => (float) $or->acumulado,
                    'apartado' => (float) $or->apartado,
                    'comprometido' => $or->comprometido,
                    'disponible' => $disponible,
                    'sobregiro' => $disponible < 0,
                    'cerrado' => $or->estaCerrado(),
                ];
            })
            ->values();
    }

    /**
     * Presupuestos (proyecto/obra/partida) para el selector de cabecera.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function presupuestosOptions(): \Illuminate\Support\Collection
    {
        return Presupuesto::with('presupuestable')
            ->get()
            ->map(function (Presupuesto $p) {
                // OP y descripción internas del presupuesto; cada una cae a la
                // de cobranza (número/descripción del presupuestable) si falta.
                $partes = array_filter([$p->opMostrar(), $p->descripcionMostrar()]);
                $label = implode(' - ', $partes);

                return [
                    'id' => $p->id,
                    'label' => $label !== '' ? $label : $p->nombreMostrar(),
                    'cerrado' => $p->estaCerrado(),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Catálogo de usos de CFDI activos para los selectores de partida.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Costos\UsoCfdi>
     */
    private function usosCfdiOptions(): \Illuminate\Support\Collection
    {
        return UsoCfdi::where('activo', true)
            ->orderBy('clave')
            ->get(['id', 'clave', 'descripcion']);
    }
}
