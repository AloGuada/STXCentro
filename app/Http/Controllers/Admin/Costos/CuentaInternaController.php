<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class CuentaInternaController extends Controller
{
    private const COSTOS_ROLES = ['compras', 'costos', 'almacen', 'contabilidad'];

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

    public function crearUsuario(Request $request): RedirectResponse
    {
        Gate::authorize('costos.cuentas-internas.editar');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(Usuario::class)],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'in:'.implode(',', self::COSTOS_ROLES)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'rol.required' => 'El rol es obligatorio.',
        ]);

        $usuario = Usuario::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $usuario->assignRole($validated['rol']);

        return back()->with('success', "Usuario {$usuario->name} creado con rol {$validated['rol']}.");
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('costos.cuentas-internas.editar');

        $validated = $request->validate([
            'usuario_id' => ['required', 'uuid', 'exists:usuarios,id'],
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
