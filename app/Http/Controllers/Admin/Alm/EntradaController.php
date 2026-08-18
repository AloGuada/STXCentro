<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\EntradaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Producto;
use App\Models\Proveedor;
use App\Services\Alm\RegistradorEntradaAlmacen;
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
 * Este controlador cubre la entrada **sin orden de compra**: material que llega
 * sin compra de por medio. La recepción contra una orden sigue capturándose
 * desde el flujo de Costos, que es donde vive la validación de lo pedido, lo
 * facturado y el presupuesto.
 */
class EntradaController extends Controller
{
    public function __construct(private readonly RegistradorEntradaAlmacen $registrador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $entradas = Entrega::query()
            ->whereIn('almacen_id', $visibles)
            ->with(['almacen:id,clave', 'ordenCompra:id,folio,proveedor_id', 'ordenCompra.proveedor:id,nombre', 'recibidoPor:id,name'])
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
                'proveedor' => $e->ordenCompra?->proveedor?->nombre,
                'renglones' => $e->detalles_count,
                'importe' => $e->importeRecibido(),
                'recibio' => $e->recibidoPor?->name,
                'cancelada' => $e->estaCancelada(),
            ]);

        return Inertia::render('admin/almacen/entradas/index', [
            'entradas' => $entradas,
            'filters' => $request->only(['almacen_id', 'search', 'ver_canceladas']),
            'almacenes' => $this->almacenes($request),
            // Lo que el almacén todavía debe recibir. Va aquí porque la
            // recepción contra orden se captura en el flujo de Costos, y desde
            // esta pantalla se llega a ella.
            'ordenesAbiertas' => $this->ordenesAbiertas(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/entradas/create', [
            'almacenes' => $this->almacenes($request),
            'proveedores' => Proveedor::query()->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'rfc']),
            'productos' => $this->productos(),
            'ordenesAbiertas' => $this->ordenesAbiertas(),
        ]);
    }

    /**
     * Sólo la entrada sin orden. La que va contra una orden pasa por
     * `Costos\EntregaController`, que además valida contra lo pedido y lo
     * facturado y ajusta el presupuesto por diferencia de precio.
     */
    public function store(EntradaStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

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
            'ordenCompra.proveedor:id,nombre,rfc',
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
                'proveedor' => $entrada->ordenCompra?->proveedor?->nombre,
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
            ->whereHas('detalles', fn ($q) => $q->whereColumn('cantidad_recibida', '<', 'cantidad'))
            ->with('proveedor:id,nombre')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'folio', 'proveedor_id', 'fecha'])
            ->map(fn (OrdenCompra $oc): array => [
                'id' => $oc->id,
                'folio' => $oc->folio,
                'proveedor' => $oc->proveedor?->nombre,
                'fecha' => $oc->fecha?->toDateString(),
            ])
            ->values()
            ->all();
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
