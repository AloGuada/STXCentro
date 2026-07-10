<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BancoRequest;
use App\Models\Banco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BancoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('costos.bancos.ver');

        return Inertia::render('admin/bancos/index', [
            'bancos' => Banco::orderByDesc('es_pagador')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.bancos.crear');

        return Inertia::render('admin/bancos/create');
    }

    public function store(BancoRequest $request): RedirectResponse
    {
        Gate::authorize('costos.bancos.crear');

        $banco = Banco::create($request->validated());
        $banco->asegurarPagadorUnico();

        return to_route('admin.bancos.index')->with('success', 'Banco creado.');
    }

    public function edit(Banco $banco): Response
    {
        Gate::authorize('costos.bancos.editar');

        return Inertia::render('admin/bancos/edit', [
            'banco' => $banco,
        ]);
    }

    public function update(BancoRequest $request, Banco $banco): RedirectResponse
    {
        Gate::authorize('costos.bancos.editar');

        $banco->update($request->validated());
        $banco->asegurarPagadorUnico();

        return to_route('admin.bancos.index')->with('success', 'Banco actualizado.');
    }

    public function destroy(Banco $banco): RedirectResponse
    {
        Gate::authorize('costos.bancos.eliminar');

        $banco->delete();

        return to_route('admin.bancos.index')->with('success', 'Banco eliminado.');
    }
}
