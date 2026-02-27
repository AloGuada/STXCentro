<?php

namespace App\Http\Controllers\Intra;

use App\Http\Controllers\Controller;
use App\Models\Intra\Area;
use App\Models\Intra\SeccionEstatica;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $secciones = SeccionEstatica::query()
            ->where('activo', true)
            ->orderBy('titulo')
            ->get(['id', 'slug', 'titulo', 'boton', 'url_externa']);

        $areas = Area::query()
            ->whereNull('parent_id')
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return Inertia::render('intra/index', [
            'secciones' => $secciones,
            'areas' => $areas,
        ]);
    }
}
