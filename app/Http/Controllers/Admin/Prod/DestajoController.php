<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\DestajoStoreRequest;
use App\Models\Concepto;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
use App\Services\Prod\GeneradorLiquidaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DestajoController extends Controller
{
    public function index(Request $request): Response
    {
        $destajos = Destajo::query()
            ->withCount('liquidaciones')
            ->when($request->search, fn ($q, $s) => $q->where('semana', 'like', "%{$s}%")->orWhere('anio', 'like', "%{$s}%"))
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/destajos/index', [
            'destajos' => $destajos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/destajos/create', [
            'anioSugerido' => now()->year,
            'semanaSugerida' => min(now()->weekOfYear, 52),
        ]);
    }

    public function store(DestajoStoreRequest $request): RedirectResponse
    {
        $destajo = Destajo::create($request->validated());

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function show(Destajo $destajo, GeneradorLiquidaciones $generador): Response
    {
        $destajo->load([
            'liquidaciones.grupoTrabajo',
            'liquidaciones.detalles.concepto',
            'liquidaciones.empleados',
            'liquidaciones.generador',
        ]);

        $data = ['destajo' => $destajo];

        if ($destajo->cerrado) {
            return Inertia::render('admin/prod/destajos/show', $data);
        }

        $data['registrosPreview'] = Registro::query()
            ->with(['concepto.obra', 'grupoTrabajo'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->orderByDesc('fecha')
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['pagosExtraPreview'] = PagoExtra::query()
            ->with(['tipo', 'grupoTrabajo'])
            ->where('destajo_id', $destajo->id)
            ->get()
            ->groupBy('grupo_trabajo_id');

        $data['piezasSinPrecio'] = $generador->piezasSinPrecio($destajo);
        $data['gruposTrabajo'] = GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get();
        $data['conceptos'] = Concepto::with('obra')->where('activo', true)->orderBy('marca')->get();
        $data['tipos'] = TipoPagoExtra::orderBy('orden')->get();

        return Inertia::render('admin/prod/destajos/show', $data);
    }

    public function destroy(Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un destajo cerrado.']);
        }

        $destajo->liquidaciones()->delete();
        $destajo->pagosExtra()->delete();
        $destajo->delete();

        return to_route('admin.prod.destajos.index');
    }

    public function cerrar(Destajo $destajo, GeneradorLiquidaciones $generador): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'Este destajo ya esta cerrado.']);
        }

        $generador->generar($destajo);

        return back();
    }
}
