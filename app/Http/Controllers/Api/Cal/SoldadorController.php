<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\SoldadorRequest;
use App\Models\Cal\Soldador;
use Illuminate\Http\JsonResponse;

class SoldadorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Soldador::all());
    }

    public function store(SoldadorRequest $request): JsonResponse
    {
        $soldador = Soldador::create($request->validated());

        return response()->json($soldador, 201);
    }

    public function show(Soldador $soldador): JsonResponse
    {
        return response()->json($soldador);
    }

    public function update(SoldadorRequest $request, Soldador $soldador): JsonResponse
    {
        $soldador->update($request->validated());

        return response()->json($soldador);
    }

    public function destroy(Soldador $soldador): JsonResponse
    {
        $soldador->delete();

        return response()->json(null, 204);
    }
}
