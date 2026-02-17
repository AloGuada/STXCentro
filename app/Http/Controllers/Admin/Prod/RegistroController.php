<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Concepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistroController extends Controller
{
    public function index(Request $request): Response
    {
        $registros = Registro::query()
            ->with(['concepto.obra', 'grupoTrabajo'])
            ->when($request->grupo_trabajo_id, fn ($q, $id) => $q->where('grupo_trabajo_id', $id))
            ->when($request->semana, function ($q, $semana) {
                $year = now()->year;
                $inicioSemana = Carbon::now()->setISODate($year, (int) $semana)->startOfWeek(Carbon::MONDAY);
                $finSemana = $inicioSemana->copy()->endOfWeek(Carbon::SUNDAY);
                $q->whereBetween('fecha', [$inicioSemana->toDateString(), $finSemana->toDateString()]);
            })
            ->when(! $request->semana && $request->fecha_inicio, fn ($q) => $q->where('fecha', '>=', $request->fecha_inicio))
            ->when(! $request->semana && $request->fecha_fin, fn ($q) => $q->where('fecha', '<=', $request->fecha_fin))
            ->orderByDesc('fecha')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/registros/index', [
            'registros' => $registros,
            'gruposTrabajo' => GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get(),
            'filters' => $request->only(['grupo_trabajo_id', 'fecha_inicio', 'fecha_fin', 'semana']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/registros/create', [
            'conceptos' => Concepto::with('obra')->where('activo', true)->orderBy('marca')->get(),
            'gruposTrabajo' => GrupoTrabajo::where('activo', true)->orderBy('descripcion')->get(),
        ]);
    }

    public function store(RegistroStoreRequest $request): RedirectResponse
    {
        Registro::create([
            'fecha' => $request->fecha,
            'concepto_id' => $request->concepto_id,
            'grupo_trabajo_id' => $request->grupo_trabajo_id,
            'cantidad' => $request->cantidad,
        ]);

        return to_route('admin.prod.registros.index');
    }

    public function destroy(Registro $registro): RedirectResponse
    {
        $registro->delete();

        return to_route('admin.prod.registros.index');
    }
}
