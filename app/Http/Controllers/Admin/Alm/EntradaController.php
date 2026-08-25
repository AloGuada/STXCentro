<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Costos\FacturaEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\EntradaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Producto;
use App\Models\Proveedor;
use App\Services\Alm\RegistradorEntradaAlmacen;
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
    ) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $entradas = Entrega::query()
            ->whereIn('almacen_id', $visibles)
            ->with(['almacen:id,clave', 'ordenCompra:id,folio,proveedor_id', 'ordenCompra.proveedor:id,razon_social,nombre_comercial', 'recibidoPor:id,name'])
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
                'recibio' => $e->recibidoPor?->name,
                'cancelada' => $e->estaCancelada(),
            ]);

        return Inertia::render('admin/almacen/entradas/index', [
            'entradas' => $entradas,
            'filters' => $request->only(['almacen_id', 'search', 'ver_canceladas']),
            'almacenes' => $this->almacenes($request),
            // Lo que el almacén todavía debe recibir: desde aquí se entra a
            // capturar la recepción de cada orden.
            'ordenesAbiertas' => $this->ordenesAbiertas(),
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
            $recepcion = $this->recepcion->registrar(
                OrdenCompra::findOrFail($request->integer('orden_compra_id')),
                $almacen,
                $request->validated(),
                (string) $request->user()->getAuthIdentifier(),
                $request->file('archivo'),
            );

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
                $producto = Producto::find($renglon['producto_id']);

                $entrada->detalles()->create([
                    'orden_compra_detalle_id' => null,
                    'producto_id' => $producto?->id,
                    // Sin orden no hay de dónde heredarlas, y el renglón tiene
                    // que poder imprimirse solo.
                    'descripcion' => $producto?->descripcion,
                    'unidad' => $producto?->unidad,
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
            'recibidoPor:id,name',
            'detalles.producto:id,codigo,descripcion,unidad',
            'detalles.ordenCompraDetalle',
        ]);

        return Inertia::render('admin/almacen/entradas/show', [
            'entrada' => [
                'id' => $entrada->id,
                'folio' => $entrada->folio,
                'fecha' => $entrada->fecha_entrega?->toDateString(),
                'almacen' => $entrada->almacen?->clave,
                'almacen_nombre' => $entrada->almacen?->nombre,
                'orden_compra_id' => $entrada->orden_compra_id,
                'orden_folio' => $entrada->ordenCompra?->folio,
                'proveedor' => $this->nombreDe($entrada->ordenCompra?->proveedor),
                'sin_orden' => $entrada->esSinOrden(),
                'observaciones' => $entrada->observaciones,
                'recibio' => $entrada->recibidoPor?->name,
                'cancelada' => $entrada->estaCancelada(),
                'motivo_cancelacion' => $entrada->motivo_cancelacion,
                'importe' => $entrada->importeRecibido(),
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
     * @return list<array<string, mixed>>
     */
    private function ordenesAbiertas(): array
    {
        return OrdenCompra::query()
            // Lo recibido no vive en la partida de la orden sino en las
            // recepciones, así que el pendiente se compara contra la suma de
            // sus renglones vigentes (los de recepciones canceladas no cuentan).
            ->whereHas('detalles', fn ($q) => $q->whereRaw(
                'costos_ordenes_compra_detalle.cantidad > ('
                .'select coalesce(sum(ed.cantidad_recibida), 0) '
                .'from costos_entrega_detalle ed '
                .'inner join costos_entregas e on e.id = ed.entrega_id '
                .'where ed.orden_compra_detalle_id = costos_ordenes_compra_detalle.id '
                .'and e.cancelada_at is null)',
            ))
            ->whereNotIn('estatus', ['cancelada', 'pagada'])
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->latest('id')
            ->limit(50)
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
