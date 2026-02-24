<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ConfiguracionDocumentoStoreRequest;
use App\Http\Requests\Admin\Cob\ConfiguracionDocumentoUpdateRequest;
use App\Models\Cob\ConfiguracionDocumento;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class ConfiguracionDocumentoController extends Controller
{
    public function store(ConfiguracionDocumentoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->configuracionDocumentos()->create($request->validated());

        return back();
    }

    public function update(ConfiguracionDocumentoUpdateRequest $request, Obra $obra, ConfiguracionDocumento $configuracionDocumento): RedirectResponse
    {
        $configuracionDocumento->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, ConfiguracionDocumento $configuracionDocumento): RedirectResponse
    {
        $configuracionDocumento->delete();

        return back();
    }
}
