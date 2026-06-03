<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DocumentoSeccionRequest;
use App\Models\Cob\DocumentoSeccion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoSeccionController extends Controller
{
    public function index(): Response
    {
        $secciones = DocumentoSeccion::query()
            ->withCount(['carpetas', 'archivos'])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('admin/cob/documento-secciones/index', [
            'secciones' => $secciones,
        ]);
    }

    public function store(DocumentoSeccionRequest $request): RedirectResponse
    {
        DocumentoSeccion::create($request->validated());

        return back();
    }

    public function update(DocumentoSeccionRequest $request, DocumentoSeccion $documentoSeccion): RedirectResponse
    {
        $documentoSeccion->update($request->validated());

        return back();
    }

    public function destroy(DocumentoSeccion $documentoSeccion): RedirectResponse
    {
        if ($documentoSeccion->archivos()->exists() || $documentoSeccion->carpetas()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una sección que ya tiene carpetas o archivos. Desactívala en su lugar.']);
        }

        $documentoSeccion->delete();

        return back();
    }
}
