<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Services\Costos\PuntosDeControl;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmacionController extends Controller
{
    public function __construct(private PuntosDeControl $puntosDeControl) {}

    /**
     * Bandeja "Por confirmar": puntos de control de Costos y Contabilidad que
     * quedan tras la cadena de firmas. A diferencia de "Mis Aprobaciones", no
     * exige firma configurada y filtra por permiso, no por asignación.
     */
    public function index(): Response
    {
        ['costos' => $costos, 'contabilidad' => $contabilidad] = $this->puntosDeControl->paraUsuario(auth()->user());

        return Inertia::render('admin/costos/confirmaciones/index', [
            'costos' => $costos,
            'contabilidad' => $contabilidad,
        ]);
    }
}
