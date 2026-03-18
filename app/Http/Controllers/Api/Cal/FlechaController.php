<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\FlechaRequest;
use App\Models\Cal\Flecha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlechaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Flecha::query();

        if ($request->has('reporte_id')) {
            $query->where('reporte_id', $request->input('reporte_id'));
        }

        return response()->json($query->get());
    }

    public function store(FlechaRequest $request): JsonResponse
    {
        $flecha = Flecha::create($request->validated());

        return response()->json($flecha, 201);
    }

    public function show(Flecha $flecha): JsonResponse
    {
        return response()->json($flecha);
    }

    public function update(FlechaRequest $request, Flecha $flecha): JsonResponse
    {
        $flecha->update($request->validated());

        return response()->json($flecha);
    }

    public function destroy(Flecha $flecha): JsonResponse
    {
        $flecha->delete();

        return response()->json(null, 204);
    }
}
