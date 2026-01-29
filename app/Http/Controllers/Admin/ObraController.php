<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ObraStoreRequest;
use App\Http\Requests\Admin\ObraUpdateRequest;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/obras/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/obras/create');
    }

    public function store(ObraStoreRequest $request): RedirectResponse
    {
        Obra::create($request->validated());

        return to_route('admin.obras.index');
    }

    public function edit(Obra $obra): Response
    {
        return Inertia::render('admin/obras/edit', [
            'obra' => $obra,
        ]);
    }

    public function update(ObraUpdateRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update($request->validated());

        return to_route('admin.obras.index');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        $obra->delete();

        return to_route('admin.obras.index');
    }
}
