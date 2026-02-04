<?php

namespace App\Http\Controllers\Intra;

use App\Http\Controllers\Controller;
use App\Models\Intra\Documento;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoController extends Controller
{
    public function show(Documento $documento): Response
    {
        if (! $documento->activo) {
            abort(404);
        }

        $documento->load(['area', 'media']);

        // Build breadcrumbs from area hierarchy
        $breadcrumbs = [];

        if ($documento->area) {
            $breadcrumbs = $documento->area->ancestors()->map(fn ($ancestor) => [
                'title' => $ancestor->descripcion,
                'href' => route('intra.area', $ancestor),
            ])->push([
                'title' => $documento->area->descripcion,
                'href' => route('intra.area', $documento->area),
            ])->all();
        }

        $breadcrumbs[] = [
            'title' => $documento->descripcion,
            'href' => route('intra.documento', $documento),
        ];

        return Inertia::render('intra/documento/show', [
            'documento' => $documento,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
