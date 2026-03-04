<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\SkillStoreRequest;
use App\Http\Requests\Admin\Rh\SkillUpdateRequest;
use App\Models\Rh\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.skills.ver');

        $skills = Skill::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/skills/index', [
            'skills' => $skills,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.skills.crear');

        return Inertia::render('admin/rh/skills/create');
    }

    public function store(SkillStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.skills.crear');

        Skill::create([
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
        ]);

        return to_route('admin.rh.skills.index');
    }

    public function edit(Skill $skill): Response
    {
        $this->authorize('rh.skills.editar');

        return Inertia::render('admin/rh/skills/edit', [
            'skill' => $skill,
        ]);
    }

    public function update(SkillUpdateRequest $request, Skill $skill): RedirectResponse
    {
        $this->authorize('rh.skills.editar');

        $skill->update([
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
        ]);

        return to_route('admin.rh.skills.index');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $this->authorize('rh.skills.eliminar');

        if ($skill->puestos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un skill asignado a puestos.']);
        }

        $skill->delete();

        return to_route('admin.rh.skills.index');
    }
}
