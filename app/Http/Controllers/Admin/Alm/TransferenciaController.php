<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\TransferenciaStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Models\Alm\Transferencia;
use App\Models\Alm\TransferenciaDetalle;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use App\Services\Alm\FirmasDelFormato;
use App\Services\Alm\RegistradorTransferencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El traslado de material entre almacenes, en dos tiempos.
 *
 * `create` es el **envío** y `show` es la **recepción**: esa pantalla no es una
 * ficha de consulta, es el segundo tiempo del documento, y por eso va detrás de
 * `alm.transferencias.recibir` y no de `.ver`. Los dos tiempos son de dos
 * personas distintas — si una sola firma alcanzara para ambos, el faltante del
 * camino lo cerraría quien lo cargó.
 */
class TransferenciaController extends Controller
{
    public function __construct(private readonly RegistradorTransferencia $registrador) {}

    public function index(Request $request): Response
    {
        $visibles = $this->almacenesVisibles($request);

        $transferencias = Transferencia::query()
            ->where(fn ($q) => $q->whereIn('almacen_origen_id', $visibles)
                ->orWhereIn('almacen_destino_id', $visibles))
            ->filtradas($request->only(['almacen_id', 'estatus', 'desde', 'hasta', 'search', 'ver_canceladas']))
            ->with(['origen:id,clave', 'destino:id,clave', 'pedido:id,folio', 'enviador:id,name', 'receptor:id,name', 'detalles'])
            ->latest('fecha_envio')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transferencia $t): array => $this->fila($t));

        return Inertia::render('admin/almacen/transferencias/index', [
            'transferencias' => $transferencias,
            'filters' => $request->only(['almacen_id', 'estatus', 'desde', 'hasta', 'search', 'ver_canceladas']),
            'almacenes' => $this->almacenes($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/transferencias/create', [
            'almacenes' => $this->almacenes($request),
            'productos' => $this->productos(),
            // Sólo los de obra: los de planta se surten con una salida. Van
            // todos los visibles, porque el pedido se elige primero y es él
            // quien dice de qué almacén sale y a cuál va.
            'pedidosTransferibles' => $this->pedidosTransferibles($request),
            'pedidoSeleccionado' => $request->integer('pedido_id') ?: null,
        ]);
    }

    public function store(TransferenciaStoreRequest $request): RedirectResponse
    {
        $origen = Almacen::findOrFail($request->integer('almacen_origen_id'));

        abort_unless($origen->esVisiblePara($request->user()), 403);

        // Con pedido, autoriza el supervisor a cuyo nombre quedó el pedido:
        // el envío lleva su firma, no la de quien lo despacha.
        $pedido = $request->filled('pedido_id') ? Pedido::find($request->integer('pedido_id')) : null;

        $transferencia = $this->registrador->enviar(
            cabecera: [
                'almacen_origen_id' => $origen->id,
                'almacen_destino_id' => $request->integer('almacen_destino_id'),
                'pedido_id' => $pedido?->id,
                'fecha_envio' => $request->date('fecha_envio'),
                'enviado_por' => $request->user()->getAuthIdentifier(),
                'autorizado_por' => $pedido?->solicitante_id ?? $request->user()->getAuthIdentifier(),
                'observaciones' => $request->input('observaciones'),
            ],
            renglones: $request->validated('detalles'),
            userId: $request->user()->getAuthIdentifier(),
        );

        return to_route('admin.alm.transferencias.show', $transferencia);
    }

    /**
     * El segundo tiempo. No es una ficha: es donde el destino captura qué bajó
     * del camión.
     */
    public function show(Request $request, Transferencia $transferencia): Response
    {
        $transferencia->load([
            'origen:id,clave,nombre',
            'destino:id,clave,nombre',
            'pedido:id,folio',
            'enviador:id,name',
            'receptor:id,name',
            'responsableFaltante:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
        ]);

        $this->autorizarVer($request, $transferencia);

        return Inertia::render('admin/almacen/transferencias/show', [
            'transferencia' => [
                ...$this->fila($transferencia),
                'observaciones' => $transferencia->observaciones,
                'motivo_cancelacion' => $transferencia->motivo_cancelacion,
                // Sólo el almacén destino confirma: quien despachó no cierra su
                // propio faltante.
                'puede_recibir' => $transferencia->vaEnCamino()
                    && $transferencia->destino?->esVisiblePara($request->user()),
            ],
            'detalles' => $transferencia->detalles->map(fn (TransferenciaDetalle $d): array => [
                'id' => $d->id,
                'codigo' => $d->articulo?->codigo,
                'descripcion' => $d->articulo?->descripcion,
                'unidad' => $d->articulo?->unidad,
                'cantidad_enviada' => (float) $d->cantidad_enviada,
                'cantidad_recibida' => $d->cantidad_recibida === null ? null : (float) $d->cantidad_recibida,
                'faltante' => $d->faltante(),
                'costo_unitario' => $d->costo_unitario === null ? null : (float) $d->costo_unitario,
                'observaciones' => $d->observaciones,
            ])->all(),
            'usuarios' => Usuario::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Cierra el documento con lo que de verdad llegó.
     *
     * Recibir de más no se acepta: el sobrante se corrige con un ajuste en el
     * destino, no ampliando el envío hacia atrás — el origen ya descargó lo que
     * descargó, y subirle al envío después falsearía su kardex.
     */
    public function recibir(Request $request, Transferencia $transferencia): RedirectResponse
    {
        abort_unless($transferencia->destino->esVisiblePara($request->user()), 403);

        if (! $transferencia->vaEnCamino()) {
            return back()->withErrors(['transferencia' => 'Esta transferencia ya se recibió o está cancelada.']);
        }

        $transferencia->load('detalles');

        $validado = $request->validate([
            'recibido' => ['required', 'array'],
            'recibido.*' => ['required', 'numeric', 'min:0'],
            'faltante_responsable_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
        ], [
            'recibido.required' => 'Captura cuánto llegó de cada renglón, aunque sea cero.',
        ]);

        $confirmado = [];
        $hayFaltante = false;
        $epsilon = (float) config('costos.epsilon_cantidad');

        foreach ($transferencia->detalles as $detalle) {
            $recibida = (float) ($validado['recibido'][$detalle->id] ?? 0);

            if ($recibida > (float) $detalle->cantidad_enviada + $epsilon) {
                return back()->withErrors([
                    'recibido' => 'No se puede recibir más de lo que se envió: el sobrante se corrige con un ajuste.',
                ])->withInput();
            }

            $confirmado[$detalle->id] = $recibida;
            $hayFaltante = $hayFaltante || $recibida < (float) $detalle->cantidad_enviada - $epsilon;
        }

        // El faltante tiene que tener dueño: para eso son los dos tiempos.
        if ($hayFaltante && empty($validado['faltante_responsable_id'])) {
            return back()->withErrors([
                'faltante_responsable_id' => 'Llegó menos de lo que salió: indica quién responde por la diferencia.',
            ])->withInput();
        }

        $this->registrador->recibir(
            $transferencia,
            $confirmado,
            $hayFaltante ? $validado['faltante_responsable_id'] : null,
            $request->user()->getAuthIdentifier(),
        );

        return back();
    }

    public function cancelar(Request $request, Transferencia $transferencia): RedirectResponse
    {
        abort_unless($transferencia->origen->esVisiblePara($request->user()), 403);

        if (! $transferencia->vaEnCamino()) {
            return back()->withErrors([
                'transferencia' => 'Ya se recibió: devolver el material es otra transferencia, no un deshacer.',
            ]);
        }

        $validado = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Escribe por qué se cancela: queda en el kardex.'],
        );

        $this->registrador->cancelar($transferencia, $validado['motivo'], $request->user()->getAuthIdentifier());

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Transferencia $t): array
    {
        $resumen = $t->resumen();

        return [
            'id' => $t->id,
            'folio' => $t->folio,
            'fecha_envio' => $t->fecha_envio?->toDateString(),
            'fecha_recepcion' => $t->fecha_recepcion?->toDateString(),
            'origen' => $t->origen?->clave,
            'origen_nombre' => $t->origen?->nombre,
            'destino' => $t->destino?->clave,
            'destino_nombre' => $t->destino?->nombre,
            'estatus' => $t->estatus->value,
            'estatus_etiqueta' => $t->estatus->etiqueta(),
            'pedido_id' => $t->pedido_id,
            'pedido_folio' => $t->pedido?->folio,
            'envio' => $t->enviador?->name,
            'recibio' => $t->receptor?->name,
            'faltante_responsable' => $t->responsableFaltante?->name,
            'renglones' => $t->detalles->count(),
            'cancelada' => $t->estaCancelada(),
            'resumen' => $resumen,
        ];
    }

    /**
     * Ve la transferencia quien puede ver cualquiera de los dos extremos: es un
     * documento de dos almacenes.
     */
    private function autorizarVer(Request $request, Transferencia $transferencia): void
    {
        $puede = $transferencia->origen?->esVisiblePara($request->user())
            || $transferencia->destino?->esVisiblePara($request->user());

        abort_unless((bool) $puede, 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pedidosTransferibles(Request $request): array
    {
        return Pedido::query()
            ->transferibles()
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->with(['almacen:id,clave', 'obra:id,no,descripcion', 'solicitante:id,name', 'detalles.articulo:id,codigo,descripcion,unidad'])
            ->orderBy('fecha_requerida')
            ->get()
            ->map(fn (Pedido $p): array => [
                'id' => $p->id,
                'folio' => $p->folio,
                'almacen_id' => $p->almacen_id,
                'almacen' => $p->almacen?->clave,
                // El supervisor que lo pidió: el envío queda autorizado por él.
                'solicitante' => $p->solicitante?->name,
                'almacen_destino_id' => $p->almacen_destino_id,
                'obra_id' => $p->obra_id,
                'obra' => $p->obra === null ? null : trim($p->obra->no.' — '.$p->obra->descripcion),
                'fecha_requerida' => $p->fecha_requerida?->toDateString(),
                'detalles' => $p->detalles
                    ->filter(fn (PedidoDetalle $d): bool => $d->pendiente() > 0)
                    ->map(fn (PedidoDetalle $d): array => [
                        'id' => $d->id,
                        'producto_id' => $d->producto_id,
                        'articulo_id' => $d->articulo_id,
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
     * Los dos extremos salen de la misma lista: el destino puede ser un almacén
     * que el usuario no administra —justo el de la obra a la que se le manda—,
     * así que aquí no se filtra por visibilidad.
     *
     * @return Collection<int, Almacen>
     */
    private function almacenes(Request $request): Collection
    {
        return Almacen::query()
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

    /**
     * La hoja viaja con el material y vuelve firmada por el destino: es el
     * comprobante de que llegó, y de cuánto llegó.
     */
    public function pdf(Request $request, Transferencia $transferencia, FirmasDelFormato $firmas): HttpResponse
    {
        abort_unless(
            $transferencia->origen->esVisiblePara($request->user())
                || $transferencia->destino->esVisiblePara($request->user()),
            403,
        );

        $transferencia->load([
            'origen:id,clave,nombre',
            'destino:id,clave,nombre',
            'pedido:id,folio',
            'autorizador:id,name',
            'enviador:id,name',
            'receptor:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-transferencia', [
            'transferencia' => $transferencia,
            'firmas' => $firmas->para(DocumentoAlm::Transferencia, $transferencia->almacen_origen_id, $transferencia),
        ])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("transferencia-{$transferencia->folio}.pdf");
    }
}
