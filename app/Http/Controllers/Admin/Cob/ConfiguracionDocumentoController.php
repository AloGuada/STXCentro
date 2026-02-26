<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ConfiguracionDocumentoStoreRequest;
use App\Http\Requests\Admin\Cob\ConfiguracionDocumentoUpdateRequest;
use App\Models\Cob\ConfiguracionDocumento;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionDocumentoController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/configuracion-documentos/create', [
            'obra' => $obra,
        ]);
    }

    public function store(ConfiguracionDocumentoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->configuracionDocumentos()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, ConfiguracionDocumento $configuracionDocumento): Response
    {
        return Inertia::render('admin/cob/configuracion-documentos/edit', [
            'obra' => $obra,
            'configuracionDocumento' => $configuracionDocumento,
        ]);
    }

    public function update(ConfiguracionDocumentoUpdateRequest $request, Obra $obra, ConfiguracionDocumento $configuracionDocumento): RedirectResponse
    {
        $configuracionDocumento->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, ConfiguracionDocumento $configuracionDocumento): RedirectResponse
    {
        $configuracionDocumento->delete();

        return back();
    }
}
