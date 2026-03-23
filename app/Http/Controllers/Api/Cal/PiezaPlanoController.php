<?php

namespace App\Http\Controllers\Api\Cal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cal\PiezaPlanoRequest;
use App\Models\Cal\PiezaPlano;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PiezaPlanoController extends Controller
{
    public function index(int $piezaId): JsonResponse
    {
        $planos = PiezaPlano::with('reportes')
            ->where('pieza_id', $piezaId)
            ->get();

        return response()->json($planos);
    }

    public function store(PiezaPlanoRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['pieza_id', 'version']);
        $piezaId = $data['pieza_id'];

        $data['pdf_path'] = $request->file('pdf')->store("cal/planos/{$piezaId}", 'local');

        if ($request->hasFile('plano_normal')) {
            $data['plano_normal'] = $request->file('plano_normal')->store("cal/planos/{$piezaId}", 'local');
        }

        if ($request->hasFile('dwg')) {
            $data['dwg_path'] = $request->file('dwg')->store("cal/planos/{$piezaId}", 'local');
        }

        $plano = PiezaPlano::create($data);

        return response()->json($plano, 201);
    }

    public function show(PiezaPlano $piezaPlano): JsonResponse
    {
        return response()->json($piezaPlano->load('reportes'));
    }

    public function update(PiezaPlanoRequest $request, PiezaPlano $piezaPlano): JsonResponse
    {
        $piezaPlano->update($request->safe()->only(['pieza_id', 'version']));

        return response()->json($piezaPlano);
    }

    public function destroy(PiezaPlano $piezaPlano): JsonResponse
    {
        if ($piezaPlano->pdf_path) {
            Storage::disk('local')->delete($piezaPlano->pdf_path);
        }
        if ($piezaPlano->plano_normal) {
            Storage::disk('local')->delete($piezaPlano->plano_normal);
        }
        if ($piezaPlano->dwg_path) {
            Storage::disk('local')->delete($piezaPlano->dwg_path);
        }

        $piezaPlano->delete();

        return response()->json(null, 204);
    }
}
