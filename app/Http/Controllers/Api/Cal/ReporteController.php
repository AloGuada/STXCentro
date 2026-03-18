<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\ReporteRequest;
use App\Models\Cal\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reporte::with(['inspector', 'soldador']);

        if ($request->has('plano_id')) {
            $query->where('plano_id', $request->input('plano_id'));
        }

        return response()->json($query->get());
    }

    public function store(ReporteRequest $request): JsonResponse
    {
        $reporte = Reporte::create($request->validated());

        return response()->json($reporte->load(['inspector', 'soldador']), 201);
    }

    public function show(Reporte $reporte): JsonResponse
    {
        return response()->json($reporte->load(['inspector', 'soldador', 'flechas']));
    }

    public function update(ReporteRequest $request, Reporte $reporte): JsonResponse
    {
        $reporte->update($request->validated());

        return response()->json($reporte->load(['inspector', 'soldador']));
    }

    public function destroy(Reporte $reporte): JsonResponse
    {
        $reporte->delete();

        return response()->json(null, 204);
    }

    public function pdf(Reporte $reporte): JsonResponse
    {
        // TODO: Implementar generación de PDF con DomPDF
        return response()->json(['message' => 'PDF generation not implemented yet'], 501);
    }

    public function copiar(Request $request, Reporte $reporte): JsonResponse
    {
        $nuevoReporte = $reporte->replicate(['aprobado', 'rechazado']);
        $nuevoReporte->consecutivo = $request->input('consecutivo', $reporte->consecutivo);
        $nuevoReporte->comentario = $request->input('comentario', $reporte->comentario);
        $nuevoReporte->es_plantilla = false;
        $nuevoReporte->save();

        foreach ($reporte->flechas as $flecha) {
            $nuevaFlecha = $flecha->replicate();
            $nuevaFlecha->reporte_id = $nuevoReporte->id;
            $nuevaFlecha->save();
        }

        return response()->json($nuevoReporte->load(['flechas', 'inspector', 'soldador']), 201);
    }

    public function deleteFlechas(Reporte $reporte): JsonResponse
    {
        $reporte->flechas()->delete();

        return response()->json(null, 204);
    }
}
