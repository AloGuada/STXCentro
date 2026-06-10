<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\PersonalCategoriaStoreRequest;
use App\Http\Requests\Admin\Cotiz\PersonalCategoriaUpdateRequest;
use App\Models\Cotiz\PersonalCategoria;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PersonalCategoriaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cotiz/personal/index', [
            'personal' => PersonalCategoria::query()->orderBy('orden')->get(),
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
