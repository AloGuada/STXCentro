<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Admin\FirmaController as FirmaDelUsuarioController;

/**
 * La misma rúbrica, desde Costos: aquí llega el aprobador al que se le exige
 * antes de firmar una solicitud, así que enseña la página con ese contexto.
 */
class FirmaController extends FirmaDelUsuarioController
{
    protected function pagina(): string
    {
        return 'admin/costos/firma/edit';
    }
}
