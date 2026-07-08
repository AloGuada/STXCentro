<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UsuarioStoreRequest;
use App\Http\Requests\Admin\UsuarioUpdateRequest;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = Usuario::query()
            ->with('roles')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/usuarios/index', [
            'usuarios' => $usuarios,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/create', [
            'roles' => Role::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(UsuarioStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $usuario = Usuario::create($data);
        $usuario->syncRoles($request->roles ?? []);

        return to_route('admin.usuarios.index');
    }

    public function edit(Usuario $usuario): Response
    {
        return Inertia::render('admin/usuarios/edit', [
            'usuario' => $usuario->load('roles'),
            'roles' => Role::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(UsuarioUpdateRequest $request, Usuario $usuario): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);
        $usuario->syncRoles($request->roles ?? []);

        return to_route('admin.usuarios.index');
    }

    public function estado(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors(['estado' => 'No puedes cambiar el estado de tu propia cuenta.']);
        }

        if ($usuario->activo) {
            $usuario->darDeBaja();
        } else {
            $usuario->reactivar();
        }

        return back();
    }

    public function destroy(Usuario $usuario): RedirectResponse
    {
        $usuario->delete();

        return to_route('admin.usuarios.index');
    }
}
