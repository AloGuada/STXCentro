<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Models\Cal\Obra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Obra::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('no', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'no' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $obra = Obra::create($validated);

        return response()->json($obra, 201);
    }

    public function show(Obra $obra): JsonResponse
    {
        return response()->json($obra);
    }

    public function update(Request $request, Obra $obra): JsonResponse
    {
        $validated = $request->validate([
            'no' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $obra->update($validated);

        return response()->json($obra);
    }

    public function destroy(Obra $obra): JsonResponse
    {
        $obra->delete();

        return response()->json(null, 204);
    }
}
