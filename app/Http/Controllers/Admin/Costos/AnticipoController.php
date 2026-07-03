<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\AnticipoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\AnticipoAplicarRequest;
use App\Http\Requests\Admin\Costos\AnticipoStoreRequest;
use App\Http\Requests\Admin\Costos\CancelarRequest;
use App\Models\Costos\Anticipo;
use App\Models\Costos\Factura;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Support\OrdenaColumnas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnticipoController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        Gate::authorize('costos.anticipos.ver');

        $query = Anticipo::query()
            ->with(['proveedor:id,razon_social,nombre_comercial', 'obra:id,descripcion'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('folio', 'like', "%{$s}%")
                    ->orWhere('referencia', 'like', "%{$s}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$s}%"));
            }))
            ->when($request->estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->proveedor_id, fn ($q, $p) => $q->where('proveedor_id', $p));

        $orden = $this->aplicarOrden($query, $request, [
            'folio' => 'folio',
            'monto' => 'monto',
            'saldo_disponible' => 'saldo_disponible',
            'moneda' => 'moneda',
            'estatus' => 'estatus',
            'fecha' => 'fecha',
            'proveedor' => fn (Builder $q, string $dir) => $q->orderBy(
                Proveedor::select('razon_social')->whereColumn('proveedores.id', 'costos_anticipos.proveedor_id'), $dir),
        ], 'created_at', 'desc');

        $anticipos = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/anticipos/index', [
            'anticipos' => $anticipos,
            'filters' => $request->only('search', 'estatus', 'proveedor_id'),
            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('razon_social')
                ->get(['id', 'razon_social']),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('costos.anticipos.crear');

        return Inertia::render('admin/costos/anticipos/create', [
            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial']),
            'obras' => Obra::orderBy('descripcion')->get(['id', 'descripcion']),
            'preset' => [
                'proveedor_id' => $request->integer('proveedor_id') ?: null,
                'obra_id' => $request->integer('obra_id') ?: null,
            ],
        ]);
    }

    public function store(AnticipoStoreRequest $request): RedirectResponse
    {
        $monto = (float) $request->input('monto');

        $anticipo = Anticipo::create([
            'proveedor_id' => $request->integer('proveedor_id'),
            'obra_id' => $request->input('obra_id'),
            'monto' => $monto,
            'saldo_disponible' => $monto, // nace con saldo completo
            'moneda' => $request->string('moneda'),
            'estatus' => AnticipoEstatus::Vigente->value,
            'referencia' => $request->input('referencia'),
            'fecha' => $request->input('fecha'),
            'notas' => $request->input('notas'),
            'creado_por' => $request->user()->id,
        ]);

        return to_route('admin.costos.anticipos.show', $anticipo)
            ->with('success', 'Anticipo registrado correctamente.');
    }

    public function show(Anticipo $anticipo): Response
    {
        Gate::authorize('costos.anticipos.ver');

        $anticipo->load([
            'proveedor:id,razon_social,nombre_comercial',
            'obra:id,descripcion',
            'creador:id,name',
            'aplicaciones.factura:id,folio,total',
            'aplicaciones.usuario:id,name',
            'pago',
            'activities.causer',
        ]);

        return Inertia::render('admin/costos/anticipos/show', [
            'anticipo' => $anticipo,
        ]);
    }

    /**
     * Aplica un anticipo a una factura. Validaciones duras:
     * - El anticipo y la factura deben pertenecer al mismo proveedor.
     * - El monto no puede exceder saldo_disponible del anticipo.
     * - El monto no puede exceder el saldo pendiente de la factura
     *   (factura.total - anticipos previos - pagos aplicados).
     */
    public function aplicar(AnticipoAplicarRequest $request): RedirectResponse
    {
        $anticipo = Anticipo::findOrFail($request->integer('anticipo_id'));
        $factura = Factura::findOrFail($request->integer('factura_id'));
        $monto = (float) $request->input('monto');

        if ($anticipo->estatus !== AnticipoEstatus::Vigente) {
            return back()->withErrors(['anticipo_id' => 'El anticipo no está vigente.']);
        }

        if ($anticipo->proveedor_id !== $factura->proveedor_id) {
            return back()->withErrors(['factura_id' => 'El anticipo y la factura deben pertenecer al mismo proveedor.']);
        }

        if ($anticipo->moneda !== $factura->moneda) {
            return back()->withErrors(['monto' => 'La moneda del anticipo no coincide con la de la factura.']);
        }

        $saldoAnticipo = (float) $anticipo->saldo_disponible;
        if ($monto > $saldoAnticipo + config('costos.epsilon_monto')) {
            return back()->withErrors([
                'monto' => sprintf('El monto excede el saldo disponible del anticipo (%.2f).', $saldoAnticipo),
            ]);
        }

        // Saldo pendiente de la factura: total - anticipos previos
        $aplicadoPrevio = (float) $factura->anticiposAplicados()->sum('monto');
        $saldoFactura = (float) $factura->total - $aplicadoPrevio;
        if ($monto > $saldoFactura + config('costos.epsilon_monto')) {
            return back()->withErrors([
                'monto' => sprintf('El monto excede el saldo pendiente de la factura (%.2f).', $saldoFactura),
            ]);
        }

        DB::transaction(function () use ($anticipo, $factura, $monto, $request) {
            $anticipo->aplicarAFactura(
                $factura,
                $monto,
                $request->user()->id,
                $request->input('notas'),
            );
        });

        return back()->with('success', sprintf(
            'Anticipo aplicado: $%s a factura %s.',
            number_format($monto, 2),
            $factura->folio,
        ));
    }

    public function cancelar(CancelarRequest $request, Anticipo $anticipo): RedirectResponse
    {
        Gate::authorize('costos.anticipos.cancelar');

        if ($anticipo->estatus !== AnticipoEstatus::Vigente) {
            return back()->withErrors(['estatus' => 'Solo se pueden cancelar anticipos vigentes.']);
        }

        if ($anticipo->aplicaciones()->exists()) {
            return back()->withErrors(['estatus' => 'No se puede cancelar un anticipo con aplicaciones registradas.']);
        }

        DB::transaction(function () use ($anticipo, $request) {
            $anticipo->transitionTo(AnticipoEstatus::Cancelado);
            $anticipo->registrarCancelacion($request->validated('motivo'), $request->user()->id);
        });

        return back()->with('success', 'Anticipo cancelado.');
    }

    /**
     * Lista anticipos vigentes del proveedor de la factura, para el modal
     * "Aplicar anticipo" en facturas/show.tsx. Filtra por moneda igual.
     */
    public function disponiblesParaFactura(Factura $factura): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('costos.anticipos.aplicar');

        $anticipos = Anticipo::query()
            ->where('proveedor_id', $factura->proveedor_id)
            ->where('moneda', $factura->moneda)
            ->where('estatus', AnticipoEstatus::Vigente->value)
            ->where('saldo_disponible', '>', 0)
            ->orderByDesc('fecha')
            ->get(['id', 'folio', 'monto', 'saldo_disponible', 'fecha', 'referencia']);

        return response()->json(['anticipos' => $anticipos]);
    }
}
