<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PresupuestoController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->withSum('obraRubros', 'presupuestado')
            ->withSum('obraRubros', 'acumulado')
            ->withCount('obraRubros')
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/presupuestos/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function edit(Obra $obra): Response
    {
        $obra->load(['obraRubros.rubro.tipoRubro']);

        $rubros = Rubro::with('tipoRubro')->orderBy('codigo')->get();

        return Inertia::render('admin/costos/presupuestos/edit', [
            'obra' => $obra,
            'rubros' => $rubros,
        ]);
    }
}
