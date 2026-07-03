<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RubroStoreRequest;
use App\Http\Requests\Admin\Costos\RubroUpdateRequest;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Departamento;
use App\Support\OrdenaColumnas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RubroController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        $query = Rubro::query()
            ->with(['tipoRubro', 'departamento'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('codigo', 'like', "%{$s}%"));

        $orden = $this->aplicarOrden($query, $request, [
            'codigo' => 'codigo',
            'descripcion' => 'descripcion',
            'ambito' => 'ambito',
            'tipo_rubro' => fn (Builder $q, string $dir) => $q->orderBy(
                TipoRubro::select('descripcion')->whereColumn('costos_tipo_rubros.id', 'costos_rubros.tipo_rubro_id'), $dir),
            'departamento' => fn (Builder $q, string $dir) => $q->orderBy(
                Departamento::select('descripcion')->whereColumn('departamentos.id', 'costos_rubros.departamento_id'), $dir),
        ], 'codigo', 'asc');

        $rubros = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/rubros/index', [
            'rubros' => $rubros,
            'filters' => $request->only('search'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/rubros/create', [
            'tipoRubros' => TipoRubro::query()->orderBy('descripcion')->get(),
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function store(RubroStoreRequest $request): RedirectResponse
    {
        Rubro::create($request->validated());

        return to_route('admin.costos.rubros.index');
    }

    public function edit(Rubro $rubro): Response
    {
        return Inertia::render('admin/costos/rubros/edit', [
            'rubro' => $rubro->load(['tipoRubro', 'departamento']),
            'tipoRubros' => TipoRubro::query()->orderBy('descripcion')->get(),
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function update(RubroUpdateRequest $request, Rubro $rubro): RedirectResponse
    {
        $rubro->update($request->validated());

        return to_route('admin.costos.rubros.index');
    }

    public function destroy(Rubro $rubro): RedirectResponse
    {
        $rubro->delete();

        return to_route('admin.costos.rubros.index');
    }
}
