<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Enums\Cob\IcsoeEstatus;
use App\Enums\Cob\IcsoeMetodo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\IcsoeStoreRequest;
use App\Http\Requests\Admin\Cob\IcsoeUpdateRequest;
use App\Models\Cob\IcsoeSbcAnio;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\Proyecto;
use App\Services\Cob\IcsoeService;
use App\Services\Cob\ValorAEjecutarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IcsoeController extends Controller
{
    public function __construct(
        private readonly IcsoeService $icsoe,
        private readonly ValorAEjecutarService $valorAEjecutar,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('cob.icsoe.ver');

        $estatus = $request->string('estatus')->toString() ?: 'todos';
        $search = $request->string('search')->toString();

        $seguimientos = IcsoeSeguimiento::query()
            ->with(['proyecto:id,no,descripcion,cliente_id', 'proyecto.cliente:id,nombre'])
            ->when($estatus !== 'todos', fn ($q) => $q->where('estatus', $estatus))
            ->when($search !== '', fn ($q) => $q->whereHas(
                'proyecto',
                fn ($p) => $p->where('no', 'like', "%{$search}%")->orWhere('descripcion', 'like', "%{$search}%")
            ))
            ->pendientesPrimero()
            ->get();

        return Inertia::render('admin/cob/icsoe/index', [
            'seguimientos' => $seguimientos,
            'proyectosSinSeguimiento' => Proyecto::query()
                ->whereDoesntHave('icsoe')
                ->orderBy('no')
                ->get(['id', 'no', 'descripcion']),
            'filters' => ['estatus' => $estatus, 'search' => $search],
            'estatusOptions' => IcsoeEstatus::options(),
            'metodoOptions' => IcsoeMetodo::options(),
        ]);
    }

    public function store(IcsoeStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        abort_if($proyecto->icsoe()->exists(), 409, 'El proyecto ya tiene un seguimiento ICSOE.');

        $seguimiento = $this->icsoe->crear($proyecto, $request->validated());

        return redirect()
            ->route('admin.cob.icsoe.show', $seguimiento)
            ->with('success', 'Seguimiento ICSOE creado.');
    }

    public function show(IcsoeSeguimiento $seguimiento): Response
    {
        $this->authorize('cob.icsoe.ver');

        $seguimiento->load(['proyecto:id,no,descripcion,cliente_id', 'proyecto.cliente:id,nombre', 'meses', 'verificadoPor:id,name']);

        return Inertia::render('admin/cob/icsoe/show', [
            'seguimiento' => $seguimiento,
            'valorAEjecutarVivo' => round($this->valorAEjecutar->paraProyecto($seguimiento->proyecto), 2),
            'metodoOptions' => IcsoeMetodo::options(),
            'sbcAnios' => IcsoeSbcAnio::query()->orderByDesc('anio')->get(['anio', 'sbc']),
        ]);
    }

    public function update(IcsoeUpdateRequest $request, IcsoeSeguimiento $seguimiento): RedirectResponse
    {
        $seguimiento->update($request->validated());

        // Cambiar método o fechas rehace el desglose, pero eso lo pidió el
        // usuario: no es un cambio externo que deba verificar.
        $this->icsoe->recalcular($seguimiento, detectarCambio: false);

        return back()->with('success', 'Seguimiento actualizado.');
    }

    public function recalcular(IcsoeSeguimiento $seguimiento): RedirectResponse
    {
        $this->authorize('cob.icsoe.editar');

        $this->icsoe->recalcular($seguimiento, 'Recálculo manual', detectarCambio: false);

        return back()->with('success', 'Seguimiento recalculado con el valor a ejecutar actual.');
    }

    public function verificar(Request $request, IcsoeSeguimiento $seguimiento): RedirectResponse
    {
        $this->authorize('cob.icsoe.verificar');

        $this->icsoe->verificar($seguimiento, $request->user());

        return back()->with('success', 'Seguimiento verificado.');
    }

    public function destroy(IcsoeSeguimiento $seguimiento): RedirectResponse
    {
        $this->authorize('cob.icsoe.eliminar');

        $seguimiento->delete();

        return redirect()
            ->route('admin.cob.icsoe.index')
            ->with('success', 'Seguimiento eliminado.');
    }
}
