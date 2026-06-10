<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\MermaStoreRequest;
use App\Http\Requests\Admin\Cotiz\MermaUpdateRequest;
use App\Models\Cotiz\Merma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MermaController extends Controller
{
    public function index(Request $request): Response
    {
        $mermas = Merma::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/mermas/index', [
            'mermas' => $mermas,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/mermas/create');
    }

    public function store(MermaStoreRequest $request): RedirectResponse
    {
        Merma::create($request->validated());

        return to_route('admin.cotiz.mermas.index');
    }

    public function edit(Merma $merma): Response
    {
        return Inertia::render('admin/cotiz/mermas/edit', [
            'merma' => $merma,
        ]);
    }

    public function update(MermaUpdateRequest $request, Merma $merma): RedirectResponse
    {
        $merma->update($request->validated());

        return to_route('admin.cotiz.mermas.index');
    }

    public function destroy(Merma $merma): RedirectResponse
    {
        $merma->delete();

        return to_route('admin.cotiz.mermas.index');
    }
}
