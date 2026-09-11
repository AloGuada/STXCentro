<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\DossierPlantilla;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El dosier, en dos pestañas: los dosieres de cada obra y el catálogo de
 * plantillas con que nacen.
 *
 * Es la versión simplificada que pidió calidad: un repositorio donde por
 * sección se suben los PDF necesarios y «Descargar» los une. Sin biblioteca
 * de documentos ni secciones que se llenen solas —de momento todo se sube a
 * mano—.
 */
class DosierController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/calidad/dosier/index', [
            'tab' => $request->query('tab') === 'catalogo' ? 'catalogo' : 'dosieres',
            'plantillaElegida' => $request->integer('plantilla') ?: null,
            'plantillas' => DossierPlantilla::query()
                ->with('secciones')
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get()
                ->map(fn (DossierPlantilla $plantilla): array => [
                    'id' => $plantilla->id,
                    'nombre' => $plantilla->nombre,
                    'descripcion' => $plantilla->descripcion,
                    'activo' => $plantilla->activo,
                    'secciones' => $plantilla->secciones->count(),
                    'arbol' => $plantilla->arbol(),
                ])
                ->all(),
        ]);
    }
}
