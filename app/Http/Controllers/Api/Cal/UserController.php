<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::all()->map(fn (Usuario $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'rol' => $u->rol,
        ]);

        return response()->json($usuarios);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'string', 'max:255'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $usuario = Usuario::create($validated);

        return response()->json([
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol,
        ], 201);
    }

    public function show(Usuario $user): JsonResponse
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->rol,
        ]);
    }

    public function update(Request $request, Usuario $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('usuarios', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'rol' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->rol,
        ]);
    }

    public function destroy(Usuario $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }
}
