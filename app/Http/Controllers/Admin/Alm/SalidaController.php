<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\SalidaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Models\Alm\Salida;
use App\Models\Alm\SalidaDetalle;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use App\Services\Alm\FirmasDelFormato;
use App\Services\Alm\RegistradorSalida;
use App\Support\HoraLocal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La entrega de material que se queda en el mismo domicilio.
 *
 * Sin `edit` ni `update`: se corrige cancelándola —lo que deja el reverso en el
 * kardex— y capturando la correcta. «Vale» es el impreso que firma quien se
 * lleva el material, no el nombre del documento.
 */
class SalidaController extends Controller
{
    public function __construct(private readonly RegistradorSalida $registrador) {}

    public function index(Request $request): Response
    {
        $salidas = Salida::query()
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->filtradas($request->only(['almacen_id', 'obra_id', 'desde', 'hasta', 'search', 'ver_canceladas']))
            ->with(['almacen:id,clave', 'obraDestino:id,no', 'pedido:id,folio', 'entregador:id,name'])
            ->withCount('detalles')
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Salida $s): array => [
                'id' => $s->id,
                'folio' => $s->folio,
                'fecha' => $s->fecha?->toDateString(),
                'almacen' => $s->almacen?->clave,
                'obra_destino' => $s->obraDestino?->no,
                'pedido_folio' => $s->pedido?->folio,
                'recibe' => $s->recibe_nombre,
                'entrego' => $s->entregador?->name,
                'renglones' => $s->detalles_count,
                'cancelada' => $s->estaCancelada(),
            ]);

        return Inertia::render('admin/almacen/salidas/index', [
            'salidas' => $salidas,
            'filters' => $request->only(['almacen_id', 'obra_id', 'desde', 'hasta', 'search', 'ver_canceladas']),
            ...$this->opciones($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $almacenId = $request->integer('almacen_id') ?: null;

        return Inertia::render('admin/almacen/salidas/create', [
            ...$this->opciones($request),
            // Solo la captura los pide, y solo cuando la salida es de planta:
            // el listado no filtra por area.
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'productos' => $this->productos(),
            // Sólo los de consumo interno: los de obra van por transferencia.
            'pedidosSurtibles' => $this->pedidosSurtibles($request, $almacenId),
            'pedidoSeleccionado' => $request->integer('pedido_id') ?: null,
        ]);
    }

    public function store(SalidaStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        // Con pedido, el solicitante es el supervisor a cuyo nombre quedó el
        // pedido, y no se cambia aquí: la salida entrega lo que él pidió.
        $pedido = $request->filled('pedido_id') ? Pedido::find($request->integer('pedido_id')) : null;

        $salida = $this->registrador->registrar(
            cabecera: [
                'almacen_id' => $almacen->id,
                'pedido_id' => $pedido?->id,
                'departamento_id' => $request->integer('departamento_id') ?: null,
                'obra_destino_id' => $request->integer('obra_destino_id') ?: null,
                'grupo_trabajo_id' => $request->integer('grupo_trabajo_id') ?: null,
                'solicitante_id' => $pedido?->solicitante_id ?? $request->input('solicitante_id'),
                'entregado_por' => $request->user()->getAuthIdentifier(),
                'recibe_nombre' => $request->string('recibe_nombre')->value(),
                'fecha' => $request->date('fecha'),
                'motivo' => $request->input('motivo'),
                'observaciones' => $request->input('observaciones'),
            ],
            renglones: $request->validated('detalles'),
            userId: $request->user()->getAuthIdentifier(),
            // Llevarse material comprometido con otra obra es la excepción, no
            // la operación: consumir lo propio y lo libre no pide nada. Sin el
            // permiso, el ledger frena con `AsignacionAjenaException`.
            permitirAjena: $request->user()->can('alm.salidas.tomar-asignado'),
        );

        return to_route('admin.alm.salidas.show', $salida);
    }

    public function show(Request $request, Salida $salida): Response
    {
        abort_unless($salida->almacen->esVisiblePara($request->user()), 403);

        $salida->load([
            'almacen:id,clave,nombre',
            'obraDestino:id,no,descripcion',
            'departamento:id,descripcion',
            'grupoTrabajo:id,descripcion',
            'pedido:id,folio',
            'solicitante:id,name',
            'entregador:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
        ]);

        return Inertia::render('admin/almacen/salidas/show', [
            'salida' => [
                'id' => $salida->id,
                'folio' => $salida->folio,
                'fecha' => $salida->fecha?->toDateString(),
                // Cuando se capturo. Es lo que responde "esto se fecho hacia atras?".
                'registrada_at' => HoraLocal::texto($salida->created_at),
                'almacen' => $salida->almacen?->clave,
                'almacen_nombre' => $salida->almacen?->nombre,
                'obra_destino' => $salida->obraDestino === null
                    ? null
                    : trim($salida->obraDestino->no.' — '.$salida->obraDestino->descripcion),
                'departamento' => $salida->departamento?->descripcion,
                'grupo_trabajo' => $salida->grupoTrabajo?->descripcion,
                'pedido_id' => $salida->pedido_id,
                'pedido_folio' => $salida->pedido?->folio,
                'solicitante' => $salida->solicitante?->name,
                'entrego' => $salida->entregador?->name,
                'recibe' => $salida->recibe_nombre,
                'motivo' => $salida->motivo,
                'observaciones' => $salida->observaciones,
                'cancelada' => $salida->estaCancelada(),
                'motivo_cancelacion' => $salida->motivo_cancelacion,
                'cancelada_at' => HoraLocal::texto($salida->cancelada_at),
            ],
            'detalles' => $salida->detalles->map(fn (SalidaDetalle $d): array => [
                'id' => $d->id,
                'codigo' => $d->articulo?->codigo,
                'descripcion' => $d->articulo?->descripcion,
                'unidad' => $d->articulo?->unidad,
                'cantidad' => (float) $d->cantidad,
                'costo_unitario' => $d->costo_unitario === null ? null : (float) $d->costo_unitario,
                'importe' => $d->importe(),
                'observaciones' => $d->observaciones,
            ])->all(),
        ]);
    }

    /**
     * El vale: la hoja que firma quien se lleva el material.
     *
     * Va sellada con su folio en código de barras porque el papel regresa
     * firmado y entonces hay que reencontrarlo en el sistema; escanearlo es un
     * tiro y teclear `SAL-2608-0007` es un dígito equivocado.
     *
     * Una salida cancelada también imprime —su folio existe y alguien puede
     * traer la hoja de vuelta—, pero el formato lo dice de frente.
     */
    public function pdf(Request $request, Salida $salida, FirmasDelFormato $firmas): HttpResponse
    {
        abort_unless($salida->almacen->esVisiblePara($request->user()), 403);

        $salida->load([
            'almacen:id,clave,nombre',
            'departamento:id,descripcion',
            'grupoTrabajo:id,descripcion',
            'pedido:id,folio',
            'entregador:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-salida', [
            'salida' => $salida,
            'firmas' => $firmas->para(DocumentoAlm::Salida, $salida->almacen_id, $salida),
        ])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("vale-{$salida->folio}.pdf");
    }

    /**
     * Cancelar devuelve el material al kardex con un movimiento espejo y hace
     * que el pedido vuelva a deber lo que esta salida decía haber entregado.
     */
    public function cancelar(Request $request, Salida $salida): RedirectResponse
    {
        abort_unless($salida->almacen->esVisiblePara($request->user()), 403);

        if ($salida->estaCancelada()) {
            return back()->withErrors(['salida' => 'Esa salida ya estaba cancelada.']);
        }

        $validado = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Escribe por qué se cancela: queda en el kardex.'],
        );

        $this->registrador->cancelar($salida, $validado['motivo'], $request->user()->getAuthIdentifier());

        return back();
    }

    /**
     * Los pedidos que esta salida puede surtir, con lo que le falta a cada
     * renglón: es lo que la pantalla precarga al elegir «surtir».
     *
     * @return list<array<string, mixed>>
     */
    private function pedidosSurtibles(Request $request, ?int $almacenId): array
    {
        return Pedido::query()
            ->surtiblesConSalida($almacenId)
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->with(['departamento:id,descripcion', 'solicitante:id,name', 'detalles.articulo:id,codigo,descripcion,unidad'])
            ->orderBy('fecha_requerida')
            ->get()
            ->map(fn (Pedido $p): array => [
                'id' => $p->id,
                'folio' => $p->folio,
                'departamento' => $p->departamento?->descripcion,
                // El supervisor que lo pidió: la salida queda a su nombre y la
                // pantalla lo enseña sin dejar cambiarlo.
                'solicitante' => $p->solicitante?->name,
                // Los ids, para que la salida herede el destino del pedido en
                // vez de volver a preguntarlo.
                'departamento_id' => $p->departamento_id,
                'grupo_trabajo_id' => $p->grupo_trabajo_id,
                'recibe' => $p->recibe_nombre,
                'fecha_requerida' => $p->fecha_requerida?->toDateString(),
                'detalles' => $p->detalles
                    ->filter(fn (PedidoDetalle $d): bool => $d->pendiente() > 0)
                    ->map(fn (PedidoDetalle $d): array => [
                        'id' => $d->id,
                        'producto_id' => $d->producto_id,
                        'codigo' => $d->articulo?->codigo,
                        'descripcion' => $d->articulo?->descripcion,
                        'unidad' => $d->articulo?->unidad,
                        'pendiente' => $d->pendiente(),
                    ])
                    ->values()
                    ->all(),
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
     * @return array<string, mixed>
     */
    private function opciones(Request $request): array
    {
        return [
            'almacenes' => Almacen::query()
                ->visiblesPara($request->user())
                ->activos()
                ->with('obra:id,no')
                ->orderBy('obra_id')
                ->orderBy('clave')
                ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']),
            'obras' => Obra::query()->orderBy('no')->get(['id', 'no', 'descripcion']),
            'gruposTrabajo' => GrupoTrabajo::query()->orderBy('descripcion')->get(['id', 'descripcion']),
        ];
    }

    /**
     * @return Collection<int, Producto>
     */
    private function productos(): Collection
    {
        // `requiere_verificacion` es dato de bodega: se lee del artículo, no
        // de la copia que el producto arrastra.
        return Producto::query()
            ->leftJoin('alm_articulos as a', 'a.item_id', '=', 'costos_productos.item_id')
            ->where('costos_productos.activo', true)
            ->orderBy('costos_productos.descripcion')
            ->get([
                'costos_productos.id', 'costos_productos.codigo', 'costos_productos.descripcion', 'costos_productos.unidad',
                DB::raw('COALESCE(a.requiere_verificacion, costos_productos.requiere_verificacion) as requiere_verificacion'),
            ]);
    }
}
