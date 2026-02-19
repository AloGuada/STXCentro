<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class CuentaInternaController extends Controller
{
    private const COSTOS_ROLES = ['compras', 'almacen', 'contabilidad'];

    public function index(): Response
    {
        Gate::authorize('costos.cuentas-internas.ver');

        $costosRoles = Role::whereIn('name', self::COSTOS_ROLES)->pluck('id');

        $usuarios = Usuario::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('role_id', $costosRoles))
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->whereIn('name', self::COSTOS_ROLES)->pluck('name'),
                'created_at' => $u->created_at,
            ]);

        $todosUsuarios = Usuario::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('admin/costos/cuentas-internas/index', [
            'usuarios' => $usuarios,
            'todosUsuarios' => $todosUsuarios,
            'costosRoles' => self::COSTOS_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('costos.cuentas-internas.editar');

        $validated = $request->validate([
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'rol' => ['required', 'in:'.implode(',', self::COSTOS_ROLES)],
        ]);

        $usuario = Usuario::findOrFail($validated['usuario_id']);
        $usuario->assignRole($validated['rol']);

        return back()->with('success', "Rol {$validated['rol']} asignado a {$usuario->name}.");
    }

    public function destroy(Usuario $usuario, string $rol): RedirectResponse
    {
        Gate::authorize('costos.cuentas-internas.editar');

        if (! in_array($rol, self::COSTOS_ROLES)) {
            return back()->withErrors(['rol' => 'Rol no válido.']);
        }

        $usuario->removeRole($rol);

        return back()->with('success', "Rol {$rol} removido de {$usuario->name}.");
    }
}
