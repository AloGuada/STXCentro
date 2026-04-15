<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dg\NotaRequest;
use App\Models\Dg\Nota;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Nota::class);

        $notas = Nota::query()
            ->where('usuario_id', request()->user()->getKey())
            ->orderByDesc('updated_at')
            ->get(['id', 'titulo', 'created_at', 'updated_at']);

        return Inertia::render('admin/dg/notas/index', [
            'notas' => $notas,
        ]);
    }

    public function store(): RedirectResponse
    {
        $this->authorize('create', Nota::class);

        $nota = Nota::query()->create([
            'usuario_id' => request()->user()->getKey(),
            'titulo' => 'Nueva nota',
            'contenido' => null,
        ]);

        return to_route('admin.dg.notas.show', $nota);
    }

    public function show(Nota $nota): Response
    {
        $this->authorize('view', $nota);

        return Inertia::render('admin/dg/notas/show', [
            'nota' => $nota->only(['id', 'titulo', 'contenido', 'created_at', 'updated_at']),
        ]);
    }

    public function update(NotaRequest $request, Nota $nota): RedirectResponse
    {
        $this->authorize('update', $nota);

        if ($request->has('titulo')) {
            $nota->titulo = trim((string) $request->input('titulo')) ?: 'Sin título';
        }
        if ($request->has('contenido')) {
            $nota->contenido = $request->input('contenido') ?: null;
        }
        $nota->save();

        return back(303);
    }

    public function destroy(Nota $nota): RedirectResponse
    {
        $this->authorize('delete', $nota);

        $nota->delete();

        return to_route('admin.dg.notas.index')->with('success', 'Nota eliminada.');
    }
}
