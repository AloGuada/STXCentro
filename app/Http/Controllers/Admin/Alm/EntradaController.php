<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\TipoFiscalPartida;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\EntradaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Producto;
use App\Models\Proveedor;
use App\Services\Alm\RegistradorEntradaAlmacen;
use App\Services\Costos\FacturaDeLaRecepcion;
use App\Services\Costos\RegistradorRecepcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La recepción de material, vista desde Almacén.
 *
 * **No es una tabla nueva**: escribe en `costos_entregas`, que ya es lo que
 * destraba la factura y ajusta el presupuesto por diferencia de precio. Lo que
 * se mudó aquí es la pantalla — una entrada aparte haría que el almacenista
 * recibiera dos veces y que dos tablas contaran lo mismo.
 *
 * Es la **única puerta de captura**: toda entrada que sube el valor del
 * inventario se levanta aquí, venga de una orden de compra (lo normal) o sin
 * orden. Costos conserva la consulta —listado, PDF, editar y cancelar—, y las
 * reglas de la recepción contra orden viven en {@see RegistradorRecepcion}.
 */
class EntradaController extends Controller
{
    public function __construct(
        private readonly RegistradorEntradaAlmacen $registrador,
        private readonly RegistradorRecepcion $recepcion,
        private readonly FacturaDeLaRecepcion $facturaDeLaRecepcion,
    ) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $entradas = Entrega::query()
            ->whereIn('almacen_id', $visibles)
            ->with(['almacen:id,clave', 'ordenCompra:id,folio,proveedor_id', 'ordenCompra.proveedor:id,razon_social,nombre_comercial', 'recibidor:id,name'])
            ->withCount('detalles')
            ->when(! $request->boolean('ver_canceladas'), fn ($q) => $q->activa())
            ->when($request->integer('almacen_id') ?: null, fn ($q, int $id) => $q->where('almacen_id', $id))
            ->when($request->string('search')->trim()->value(), fn ($q, string $s) => $q->where('folio', 'like', "%{$s}%"))
            ->latest('fecha_entrega')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Entrega $e): array => [
                'id' => $e->id,
                'folio' => $e->folio,
                'fecha' => $e->fecha_entrega?->toDateString(),
                'almacen' => $e->almacen?->clave,
                'orden_compra_id' => $e->orden_compra_id,
                'orden_folio' => $e->ordenCompra?->folio,
                'proveedor' => $this->nombreDe($e->ordenCompra?->proveedor),
                'renglones' => $e->detalles_count,
                'importe' => $e->importeRecibido(),
                'recibio' => $e->recibidor?->name,
                'cancelada' => $e->estaCancelada(),
            ]);

        return Inertia::render('admin/almacen/entradas/index', [
            'entradas' => $entradas,
            'filters' => $request->only(['almacen_id', 'search', 'ver_canceladas']),
            'almacenes' => $this->almacenes($request),
            // El listado sólo anuncia cuántas órdenes esperan material; elegir
            // una es cosa de la pantalla de captura, así que aquí basta el
            // número.
            'ordenesAbiertasCount' => $this->porRecibir()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/entradas/create', [
            'almacenes' => $this->almacenes($request),
            'proveedores' => Proveedor::query()
                ->orderBy('razon_social')
                ->limit(500)
                ->get(['id', 'razon_social', 'nombre_comercial', 'rfc'])
                ->map(fn (Proveedor $p): array => [
                    'id' => $p->id,
                    'nombre' => $this->nombreDe($p),
                    'rfc' => $p->rfc,
                ]),
            'productos' => $this->productos(),
            'ordenesAbiertas' => $this->ordenesAbiertas(),
            'orden' => $this->ordenParaRecibir($request->integer('orden_compra_id') ?: null),
        ]);
    }

    /**
     * La orden que se está recibiendo: sus partidas con lo que todavía falta y
     * las facturas que esperan recepción. Null cuando la entrada no cuelga de
     * ninguna orden.
     *
     * @return array<string, mixed>|null
     */
    private function ordenParaRecibir(?int $ordenCompraId): ?array
    {
        if ($ordenCompraId === null) {
            return null;
        }

        $orden = OrdenCompra::query()
            ->with([
                'proveedor:id,razon_social,nombre_comercial',
                'detalles.producto:id,codigo,controla_inventario',
                'facturas:id,orden_compra_id,folio,folio_fiscal,total,estatus',
            ])
            ->findOrFail($ordenCompraId);

        $recibido = EntregaDetalle::query()
            ->whereIn('orden_compra_detalle_id', $orden->detalles->pluck('id'))
            ->whereHas('entrega', fn ($q) => $q->activa())
            ->selectRaw('orden_compra_detalle_id, SUM(cantidad_recibida) as total')
            ->groupBy('orden_compra_detalle_id')
            ->pluck('total', 'orden_compra_detalle_id');

        return [
            'id' => $orden->id,
            'folio' => $orden->folio,
            'moneda' => $orden->moneda,
            'proveedor' => $this->nombreDe($orden->proveedor),
            // Lo que la orden le carga de impuestos a su propio subtotal, para
            // que la pantalla pueda anticipar el total contra el que se cuadra
            // la factura. Es una estimación: quien manda es el cálculo del
            // servidor, que además pesa partidas exentas y retenciones.
            'factor_impuestos' => $this->factorImpuestos($orden),
            'partidas' => $orden->detalles->map(fn ($partida): array => [
                'id' => $partida->id,
                'descripcion' => $partida->descripcion,
                'codigo' => $partida->producto?->codigo ?? $partida->codigo_producto,
                'unidad' => $partida->unidad,
                'cantidad' => (float) $partida->cantidad,
                'recibido' => (float) ($recibido[$partida->id] ?? 0),
                'pendiente' => round((float) $partida->cantidad - (float) ($recibido[$partida->id] ?? 0), 4),
                'precio_unitario' => (float) $partida->precio_unitario,
                // Lo que no lleva kardex se recibe igual —destraba la factura—
                // pero no mueve existencia, y la pantalla lo avisa.
                'mueve_kardex' => $partida->producto?->controla_inventario ?? false,
            ])->values()->all(),
            'facturas' => $orden->facturas
                ->where('estatus', FacturaEstatus::PendienteRecepcion)
                ->map(fn ($factura): array => [
                    'id' => $factura->id,
                    'folio' => $factura->folio_fiscal ?: $factura->folio,
                    'total' => (float) $factura->total,
                ])->values()->all(),
        ];
    }

    /**
     * Toda entrada que sube el valor del inventario pasa por aquí: la que viene
     * de una orden de compra —el caso normal, material de proveedor— y la que
     * llega sin compra de por medio.
     *
     * La primera se delega a {@see RegistradorRecepcion}, donde viven las
     * reglas de Costos (tope contra lo pedido, ajuste de presupuesto por
     * diferencia de precio y avance de la factura); Almacén pone el almacén,
     * que es lo que mueve el kardex.
     */
    public function store(EntradaStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        if ($request->esConOrden()) {
            $orden = OrdenCompra::findOrFail($request->integer('orden_compra_id'));

            // Primero, que los renglones sean recibibles: cuadrar la factura
            // contra material que no va a entrar mandaría al almacenista a
            // corregir el número equivocado.
            $this->recepcion->validarCaptura($orden, $request->validated('detalles'));

            // La factura y la recepción se escriben juntas o no se escribe
            // ninguna: cada servicio abre su propia transacción, y sin esta de
            // afuera una recepción rechazada dejaría viva la factura que
            // acababa de nacer.
            $recepcion = DB::transaction(function () use ($request, $orden, $almacen): Entrega {
                $factura = $this->facturaDeLaRecepcion->resolver(
                    $orden,
                    $this->lineasRecibidas($orden, $request->validated('detalles')),
                    $request->file('xml'),
                    $request->file('pdf'),
                    $request->integer('factura_id') ?: null,
                );

                return $this->recepcion->registrar(
                    $orden,
                    $almacen,
                    [...$request->validated(), 'factura_id' => $factura->id],
                    (string) $request->user()->getAuthIdentifier(),
                );
            });

            return to_route('admin.alm.entradas.show', $recepcion);
        }

        $entrada = DB::transaction(function () use ($request, $almacen): Entrega {
            $entrada = Entrega::create([
                'orden_compra_id' => null,
                'almacen_id' => $almacen->id,
                'recibido_por' => $request->user()->getAuthIdentifier(),
                'fecha_entrega' => $request->date('fecha_entrega'),
                'tipo' => 'completa',
                'observaciones' => $request->input('observaciones'),
            ]);

            foreach ($request->validated('detalles') as $renglon) {
                // El renglón se captura por artículo y se guarda por producto:
                // el documento es de compra. La descripción sale del artículo,
                // que es lo que el almacenista tenía enfrente al recibir.
                $articulo = Articulo::find($renglon['articulo_id']);

                $entrada->detalles()->create([
                    'orden_compra_detalle_id' => null,
                    'producto_id' => $articulo?->producto_id,
                    // Sin orden no hay de dónde heredarlas, y el renglón tiene
                    // que poder imprimirse solo.
                    'descripcion' => $articulo?->descripcion,
                    'unidad' => $articulo?->unidad,
                    'cantidad_recibida' => $renglon['cantidad_recibida'],
                    'precio_unitario' => $renglon['precio_unitario'],
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);
            }

            $this->registrador->aplicar($entrada->load('detalles'), $request->user()->getAuthIdentifier());

            return $entrada;
        });

        return to_route('admin.alm.entradas.show', $entrada);
    }

    /**
     * Cuánto crece el subtotal de la orden al llegar a su total: 1.16 en una
     * orden de mercancía normal, menos si trae retenciones, 1 si todo es exento.
     * Sirve de anticipo en la pantalla, no de regla.
     */
    private function factorImpuestos(OrdenCompra $orden): float
    {
        $subtotal = (float) $orden->detalles->sum(
            fn ($partida): float => (float) $partida->cantidad * (float) $partida->precio_unitario,
        );

        return $subtotal > 0 ? round((float) $orden->total / $subtotal, 6) : 1.0;
    }

    /**
     * Lo que ampara esta captura, en la forma que pide el cálculo de impuestos:
     * un renglón por partida recibida, con su tratamiento fiscal. Es contra el
     * total de esto que se contrasta la factura.
     *
     * Se arma desde lo capturado porque los renglones todavía no existen, con el
     * mismo criterio de precio que después usará
     * {@see \App\Models\Costos\EntregaDetalle::precio_unitario_efectivo}: el
     * capturado si lo hay, el de la partida si no.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array{tipo_fiscal: string, subtotal: float, sin_impuestos: bool}>
     */
    private function lineasRecibidas(OrdenCompra $orden, array $detalles): array
    {
        $partidas = $orden->detalles()->get()->keyBy('id');

        $lineas = [];

        foreach ($detalles as $renglon) {
            $partida = $partidas->get((int) $renglon['orden_compra_detalle_id']);

            if ($partida === null) {
                continue;
            }

            $precio = isset($renglon['precio_unitario']) && $renglon['precio_unitario'] !== ''
                ? (float) $renglon['precio_unitario']
                : (float) $partida->precio_unitario;

            $lineas[] = [
                'tipo_fiscal' => $partida->tipo_fiscal instanceof \BackedEnum
                    ? (string) $partida->tipo_fiscal->value
                    : (string) ($partida->tipo_fiscal ?? TipoFiscalPartida::Mercancia->value),
                'subtotal' => round((float) $renglon['cantidad_recibida'] * $precio, 2),
                'sin_impuestos' => (bool) $partida->sin_impuestos,
            ];
        }

        return $lineas;
    }

    public function show(Request $request, Entrega $entrada): Response
    {
        abort_unless(
            $entrada->almacen !== null && $entrada->almacen->esVisiblePara($request->user()),
            403,
        );

        $entrada->load([
            'almacen:id,clave,nombre',
            'ordenCompra:id,folio,proveedor_id',
            'ordenCompra.proveedor:id,razon_social,nombre_comercial,rfc',
            'recibidor:id,name',
            'detalles.producto:id,codigo,descripcion,unidad',
            'detalles.ordenCompraDetalle',
            'factura:id,folio,folio_fiscal,uuid_fiscal,total',
            'factura.mediaXml',
            'factura.mediaPdf',
        ]);

        return Inertia::render('admin/almacen/entradas/show', [
            'entrada' => [
                'id' => $entrada->id,
                'folio' => $entrada->folio,
                'fecha' => $entrada->fecha_entrega?->toDateString(),
                // Cuando se capturo. Es lo que responde "esto se fecho hacia atras?".
                'registrada_at' => $entrada->created_at?->toDateTimeString(),
                'almacen' => $entrada->almacen?->clave,
                'almacen_nombre' => $entrada->almacen?->nombre,
                'orden_compra_id' => $entrada->orden_compra_id,
                'orden_folio' => $entrada->ordenCompra?->folio,
                'proveedor' => $this->nombreDe($entrada->ordenCompra?->proveedor),
                'sin_orden' => $entrada->esSinOrden(),
                'observaciones' => $entrada->observaciones,
                'recibio' => $entrada->recibidor?->name,
                'cancelada' => $entrada->estaCancelada(),
                'motivo_cancelacion' => $entrada->motivo_cancelacion,
                'importe' => $entrada->importeRecibido(),
                // El respaldo fiscal de la recepción. Vive colgado de la
                // factura, no de la entrada, pero se consulta desde aquí: es
                // donde el almacenista lo subió.
                'factura' => $entrada->factura === null ? null : [
                    'id' => $entrada->factura->id,
                    'folio' => $entrada->factura->folio_fiscal ?: $entrada->factura->folio,
                    'total' => (float) $entrada->factura->total,
                    'xml_path' => $entrada->factura->mediaXml?->path,
                    'pdf_path' => $entrada->factura->mediaPdf?->path,
                ],
            ],
            'detalles' => $entrada->detalles->map(fn (EntregaDetalle $d): array => [
                'id' => $d->id,
                'codigo' => $d->producto?->codigo,
                'descripcion' => $d->descripcion ?? $d->producto?->descripcion ?? $d->ordenCompraDetalle?->descripcion,
                'unidad' => $d->unidad ?? $d->producto?->unidad,
                'cantidad' => (float) $d->cantidad_recibida,
                'precio_unitario' => (float) $d->precio_unitario_efectivo,
                'importe' => (float) $d->cantidad_recibida * (float) $d->precio_unitario_efectivo,
                // Lo que se recibió pero no movió existencia: sin artículo, o un
                // servicio, o algo que Compras tecleó sin código. La pantalla lo
                // avisa o el material entra sin quedar en el kardex.
                'mueve_kardex' => $d->producto?->controla_inventario ?? false,
                'observaciones' => $d->observaciones,
            ])->all(),
        ]);
    }

    /**
     * Las órdenes que todavía deben material, para llegar desde aquí a la
     * recepción contra orden.
     *
     * Va sin tope: la pantalla las busca en vez de recorrerlas, y un `limit`
     * dejaba fuera órdenes sin decirlo ni dar forma de llegar a ellas. Sólo se
     * excluye la cancelada; una orden pagada por adelantado que no se recibió
     * completa no está completada y sí se puede recibir.
     *
     * @return list<array<string, mixed>>
     */
    private function ordenesAbiertas(): array
    {
        return $this->porRecibir()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->orderBy('fecha_entrega_esperada')
            ->get(['id', 'folio', 'proveedor_id', 'fecha_entrega_esperada'])
            ->map(fn (OrdenCompra $oc): array => [
                'id' => $oc->id,
                'folio' => $oc->folio,
                'proveedor' => $this->nombreDe($oc->proveedor),
                'fecha' => $oc->fecha_entrega_esperada?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<OrdenCompra>
     */
    private function porRecibir(): \Illuminate\Database\Eloquent\Builder
    {
        return OrdenCompra::query()
            ->pendientesDeRecibir()
            ->where('estatus', '!=', OrdenCompraEstatus::Cancelada->value);
    }

    /** El proveedor no tiene columna `nombre`: se arma con lo que sí existe. */
    private function nombreDe(?Proveedor $proveedor): ?string
    {
        if ($proveedor === null) {
            return null;
        }

        return $proveedor->nombre_comercial ?: $proveedor->razon_social;
    }

    /**
     * @return Collection<int, int>
     */
    private function almacenesVisibles(Request $request): Collection
    {
        return Almacen::query()->visiblesPara($request->user())->pluck('id');
    }

    /**
     * @return Collection<int, Almacen>
     */
    private function almacenes(Request $request): Collection
    {
        return Almacen::query()
            ->visiblesPara($request->user())
            ->activos()
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);
    }

    /**
     * @return Collection<int, Producto>
     */
    private function productos(): Collection
    {
        return Producto::query()
            ->deInventario()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion', 'unidad', 'requiere_verificacion']);
    }
}
