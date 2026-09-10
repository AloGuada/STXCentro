<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\PedidoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\PedidoStoreRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que un área le pide a un almacén.
 *
 * Nace **aprobado**: la matriz de aprobadores quedó fuera de alcance, así que
 * por ahora levantar el pedido es pedirlo. Cuando exista, sólo cambia el estatus
 * inicial y `aprobar` deja de ser una acción suelta.
 */
class PedidoController extends Controller
{
    public function index(Request $request): Response
    {
        $pedidos = Pedido::query()
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->filtrados($request->only(['almacen_id', 'obra_id', 'departamento_id', 'estatus', 'destino', 'search']))
            ->with([
                'almacen:id,clave',
                'obra:id,no,descripcion',
                'departamento:id,descripcion',
                'solicitante:id,name',
                'detalles',
            ])
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pedido $p): array => $this->fila($p));

        return Inertia::render('admin/almacen/pedidos/index', [
            'pedidos' => $pedidos,
            'filters' => $request->only(['almacen_id', 'obra_id', 'departamento_id', 'estatus', 'destino', 'search']),
            'estatuses' => array_map(
                fn (PedidoEstatus $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                PedidoEstatus::cases(),
            ),
            ...$this->opciones($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/almacen/pedidos/create', [
            ...$this->opciones($request),
            'productos' => $this->productos(),
        ]);
    }

    public function store(PedidoStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        $pedido = DB::transaction(function () use ($request, $almacen): Pedido {
            $pedido = Pedido::create([
                'almacen_id' => $almacen->id,
                'departamento_id' => $request->integer('departamento_id'),
                'obra_id' => $request->integer('obra_id') ?: null,
                'solicitante_id' => $request->user()->getAuthIdentifier(),
                'recibe_nombre' => $request->input('recibe_nombre'),
                'grupo_trabajo_id' => $request->integer('grupo_trabajo_id') ?: null,
                'fecha' => $request->date('fecha'),
                'fecha_requerida' => $request->date('fecha_requerida'),
                'motivo' => $request->input('motivo'),
                'observaciones' => $request->input('observaciones'),
                // Sin matriz de aprobadores, levantar el pedido es pedirlo.
                'estatus' => PedidoEstatus::Aprobado,
                'aprobado_por' => $request->user()->getAuthIdentifier(),
                'aprobado_at' => now(),
            ]);

            foreach ($request->validated('detalles') as $renglon) {
                $pedido->detalles()->create([
                    'articulo_id' => $renglon['articulo_id'],
                    'cantidad_solicitada' => $renglon['cantidad_solicitada'],
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);
            }

            return $pedido;
        });

        return to_route('admin.alm.pedidos.show', $pedido);
    }

    public function show(Request $request, Pedido $pedido): Response
    {
        abort_unless($pedido->almacen->esVisiblePara($request->user()), 403);

        $pedido->load([
            'almacen:id,clave,nombre',
            'obra:id,no,descripcion',
            'departamento:id,descripcion',
            'solicitante:id,name',
            'grupoTrabajo:id,descripcion',
            'detalles.articulo:id,codigo,descripcion,unidad,tipo',
        ]);

        return Inertia::render('admin/almacen/pedidos/show', [
            'pedido' => [
                ...$this->fila($pedido),
                'motivo' => $pedido->motivo,
                'observaciones' => $pedido->observaciones,
                'grupo_trabajo' => $pedido->grupoTrabajo?->descripcion,
                'motivo_rechazo' => $pedido->motivo_rechazo,
            ],
            'detalles' => $pedido->detalles->map(fn (PedidoDetalle $d): array => [
                'id' => $d->id,
                'producto_id' => $d->producto_id,
                'codigo' => $d->articulo?->codigo,
                'descripcion' => $d->articulo?->descripcion,
                'unidad' => $d->articulo?->unidad,
                'cantidad_solicitada' => (float) $d->cantidad_solicitada,
                'cantidad_surtida' => (float) $d->cantidad_surtida,
                'pendiente' => $d->pendiente(),
                'observaciones' => $d->observaciones,
            ])->all(),
        ]);
    }

    /**
     * Cancelar es del solicitante: el pedido deja de aparecer entre lo que el
     * almacén debe. Lo ya surtido no se devuelve — para eso está la
     * transferencia de sobrante.
     */
    public function cancelar(Request $request, Pedido $pedido): RedirectResponse
    {
        abort_unless($pedido->almacen->esVisiblePara($request->user()), 403);

        if ($pedido->estatus->estaCerrado()) {
            return back()->withErrors(['pedido' => 'Ese pedido ya está cerrado.']);
        }

        $validado = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Escribe por qué se cancela: queda en el documento.'],
        );

        $pedido->update([
            'estatus' => PedidoEstatus::Cancelado,
            'motivo_rechazo' => $validado['motivo'],
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Pedido $pedido): array
    {
        $detalles = $pedido->detalles;

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'fecha' => $pedido->fecha?->toDateString(),
            'fecha_requerida' => $pedido->fecha_requerida?->toDateString(),
            'almacen' => $pedido->almacen?->clave,
            'almacen_id' => $pedido->almacen_id,
            'departamento' => $pedido->departamento?->descripcion,
            'obra' => $pedido->obra === null
                ? null
                : trim($pedido->obra->no.' — '.$pedido->obra->descripcion),
            'recibe' => $pedido->recibe_nombre,
            'solicitante' => $pedido->solicitante?->name,
            'estatus' => $pedido->estatus->value,
            'estatus_etiqueta' => $pedido->estatus->etiqueta(),
            'renglones' => $detalles->count(),
            // Con obra hay que llevarlo a otro domicilio; sin obra se queda aquí.
            // De eso depende con qué documento se surte, y la pantalla lo dice.
            'se_surte_con' => $pedido->seSurteConTransferencia() ? 'transferencia' : 'salida',
            // La herramienta no se surte sacándola: se presta. Un pedido puede
            // traer de las dos cosas, y entonces ofrece los dos caminos.
            'pide_herramienta' => $pedido->pideHerramienta(),
            'avance' => [
                'solicitado' => (float) $detalles->sum('cantidad_solicitada'),
                'surtido' => (float) $detalles->sum('cantidad_surtida'),
                'renglones_pendientes' => $detalles
                    ->filter(fn (PedidoDetalle $d): bool => $d->pendiente() > 0)
                    ->count(),
            ],
        ];
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
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'obras' => Obra::query()->orderBy('no')->get(['id', 'no', 'descripcion']),
            'gruposTrabajo' => GrupoTrabajo::query()->orderBy('descripcion')->get(['id', 'descripcion']),
        ];
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

    /**
     * El formato impreso, que es donde el pedido se autoriza: el módulo no
     * tiene flujo de aprobación y la firma va en la hoja.
     */
    public function pdf(Request $request, Pedido $pedido): HttpResponse
    {
        abort_unless($pedido->almacen->esVisiblePara($request->user()), 403);

        $pedido->load([
            'almacen:id,clave,nombre',
            'departamento:id,descripcion',
            'obra:id,no,descripcion',
            'grupoTrabajo:id,descripcion',
            'solicitante:id,name',
            'aprobador:id,name',
            'detalles.articulo:id,codigo,descripcion,unidad,tipo',
        ]);

        $pdf = Pdf::loadView('pdf.alm.formato-pedido', ['pedido' => $pedido])
            ->setPaper('letter', 'portrait');

        return $pdf->stream("pedido-{$pedido->folio}.pdf");
    }
}
