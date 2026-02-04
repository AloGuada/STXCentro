<?php

namespace App\Http\Controllers\Intra;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Models\Intra\Area;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function show(Area $area): Response
    {
        if (! $area->activo) {
            abort(404);
        }

        $area->load([
            'parent',
            'children' => fn ($q) => $q->where('activo', true)->orderBy('descripcion'),
            'documentos' => fn ($q) => $q->where('activo', true)->with('media')->orderBy('order')->orderBy('descripcion'),
        ]);

        // Group documents by type in the correct order
        $documentosPorTipo = collect(TipoDocumento::cases())
            ->sortBy(fn (TipoDocumento $tipo) => $tipo->order())
            ->mapWithKeys(function (TipoDocumento $tipo) use ($area) {
                $docs = $area->documentos->filter(fn ($doc) => $doc->tipo === $tipo);

                return $docs->isNotEmpty() ? [$tipo->value => [
                    'label' => $tipo->label(),
                    'documentos' => $docs->values(),
                ]] : [];
            })
            ->filter();

        // Build breadcrumbs
        $breadcrumbs = $area->ancestors()->map(fn (Area $ancestor) => [
            'title' => $ancestor->descripcion,
            'href' => route('intra.area', $ancestor),
        ])->push([
            'title' => $area->descripcion,
            'href' => route('intra.area', $area),
        ]);

        return Inertia::render('intra/area/show', [
            'area' => $area,
            'documentosPorTipo' => $documentosPorTipo,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
