<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ClienteStoreRequest;
use App\Http\Requests\Admin\Cob\ClienteUpdateRequest;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $clientes = Cliente::query()
            ->withCount('contactos')
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%")
                ->orWhere('rfc', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/cob/clientes/index', [
            'clientes' => $clientes,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cob/clientes/create');
    }

    public function store(ClienteStoreRequest $request): RedirectResponse
    {
        Cliente::create($request->validated());

        return to_route('admin.cob.clientes.index');
    }

    public function edit(Cliente $cliente): Response
    {
        $cliente->load('contactos');

        return Inertia::render('admin/cob/clientes/edit', [
            'cliente' => $cliente,
        ]);
    }

    public function update(ClienteUpdateRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return to_route('admin.cob.clientes.index');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        if ($cliente->obras()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un cliente con obras asociadas.']);
        }

        $cliente->delete();

        return to_route('admin.cob.clientes.index');
    }
}
