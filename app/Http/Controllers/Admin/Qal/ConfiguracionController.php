<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ConfiguracionQalUpdateRequest;
use App\Models\Qal\ConfiguracionQal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/calidad/configuracion/edit', [
            'configuracion' => ConfiguracionQal::actual(),
        ]);
    }

    public function update(ConfiguracionQalUpdateRequest $request): RedirectResponse
    {
        ConfiguracionQal::actual()->update($request->validated());

        return back()->with('success', 'Configuración guardada.');
    }
}
