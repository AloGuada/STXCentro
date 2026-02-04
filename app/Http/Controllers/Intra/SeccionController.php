<?php

namespace App\Http\Controllers\Intra;

use App\Http\Controllers\Controller;
use App\Models\Intra\SeccionEstatica;
use Inertia\Inertia;
use Inertia\Response;

class SeccionController extends Controller
{
    public function show(SeccionEstatica $seccion): Response
    {
        if (! $seccion->activo) {
            abort(404);
        }

        $seccion->load('media');

        return Inertia::render('intra/seccion/show', [
            'seccion' => $seccion,
        ]);
    }
}
