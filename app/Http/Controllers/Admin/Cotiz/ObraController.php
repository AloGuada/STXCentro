<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraStoreRequest;
use App\Http\Requests\Admin\Cotiz\ObraUpdateRequest;
use App\Models\Cotiz\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $obras = Obra::query()
            ->withCount('generadoras')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('op', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/obras/index', [
            'obras' => $obras,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/obras/create');
    }

    public function store(ObraStoreRequest $request): RedirectResponse
    {
        Obra::create($request->validated());

        return to_route('admin.cotiz.obras.index');
    }

    public function edit(Obra $obra): Response
    {
        return Inertia::render('admin/cotiz/obras/edit', [
            'obra' => $obra->loadCount('generadoras'),
        ]);
    }

    public function update(ObraUpdateRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update($request->validated());

        return to_route('admin.cotiz.obras.index');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        $obra->delete();

        return to_route('admin.cotiz.obras.index');
    }
}
