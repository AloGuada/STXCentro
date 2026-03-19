<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\EtapaRequest;
use App\Models\Cal\Etapa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtapaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Etapa::with(['obra', 'piezas.planos.reportes.inspector', 'piezas.planos.reportes.soldador']);

        if ($request->has('obra_id')) {
            $query->where('obra_id', $request->input('obra_id'));
        }

        return response()->json($query->get());
    }

    public function store(EtapaRequest $request): JsonResponse
    {
        $etapa = Etapa::create($request->validated());

        return response()->json($etapa->load('obra'), 201);
    }

    public function show(Etapa $etapa): JsonResponse
    {
        return response()->json($etapa->load(['obra', 'piezas.planos.reportes.inspector', 'piezas.planos.reportes.soldador']));
    }

    public function update(EtapaRequest $request, Etapa $etapa): JsonResponse
    {
        $etapa->update($request->validated());

        return response()->json($etapa->load('obra'));
    }

    public function destroy(Etapa $etapa): JsonResponse
    {
        $etapa->delete();

        return response()->json(null, 204);
    }
}
