<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\PiezaRequest;
use App\Models\Cal\Pieza;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PiezaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pieza::with('planos');

        if ($request->has('etapa_id')) {
            $query->where('etapa_id', $request->input('etapa_id'));
        }

        return response()->json($query->get());
    }

    public function store(PiezaRequest $request): JsonResponse
    {
        $pieza = Pieza::create($request->validated());

        return response()->json($pieza, 201);
    }

    public function show(Pieza $pieza): JsonResponse
    {
        return response()->json($pieza->load('planos'));
    }

    public function update(PiezaRequest $request, Pieza $pieza): JsonResponse
    {
        $pieza->update($request->validated());

        return response()->json($pieza->load('planos'));
    }

    public function destroy(Pieza $pieza): JsonResponse
    {
        $pieza->delete();

        return response()->json(null, 204);
    }
}
