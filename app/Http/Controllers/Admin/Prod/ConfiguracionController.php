<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\ConfiguracionProdUpdateRequest;
use App\Models\Prod\ConfiguracionProd;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/prod/configuracion/edit', [
            'configuracion' => ConfiguracionProd::actual(),
        ]);
    }

    public function update(ConfiguracionProdUpdateRequest $request): RedirectResponse
    {
        ConfiguracionProd::actual()->update($request->validated());

        return back()->with('success', 'Configuracion guardada.');
    }
}
