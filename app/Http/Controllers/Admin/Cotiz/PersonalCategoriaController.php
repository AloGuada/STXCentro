<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\PersonalCategoriaStoreRequest;
use App\Http\Requests\Admin\Cotiz\PersonalCategoriaUpdateRequest;
use App\Models\Cotiz\PersonalCategoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalCategoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $personal = PersonalCategoria::query()
            ->when($request->search, fn ($q, $s) => $q->where('codigo', 'like', "%{$s}%")
                ->orWhere('nombre', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cotiz/personal/index', [
            'personal' => $personal,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cotiz/personal/create');
    }

    public function store(PersonalCategoriaStoreRequest $request): RedirectResponse
    {
        PersonalCategoria::create($request->validated());

        return to_route('admin.cotiz.personal.index');
    }

    public function edit(PersonalCategoria $personal): Response
    {
        return Inertia::render('admin/cotiz/personal/edit', [
            'personal' => $personal,
        ]);
    }

    public function update(PersonalCategoriaUpdateRequest $request, PersonalCategoria $personal): RedirectResponse
    {
        $personal->update($request->validated());

        return to_route('admin.cotiz.personal.index');
    }

    public function destroy(PersonalCategoria $personal): RedirectResponse
    {
        $personal->delete();

        return to_route('admin.cotiz.personal.index');
    }
}
