<?php

namespace App\Policies\Dg;

use App\Models\Dg\Carpeta;
use App\Models\Usuario;

class CarpetaPolicy
{
    public function view(Usuario $user, Carpeta $carpeta): bool
    {
        return $this->viewCarpeta($user, $carpeta);
    }

    public function viewCarpeta(Usuario $user, Carpeta $carpeta): bool
    {
        if (! $user->can('dg.reportes.ver')) {
            return false;
        }

        return $carpeta->usuarioTieneAcceso($user);
    }

    public function createForCarpeta(Usuario $user, Carpeta $carpeta): bool
    {
        return $carpeta->usuarioPuedeEscribir($user);
    }

    public function gestionarAccesos(Usuario $user): bool
    {
        return $user->can('dg.reportes.administrar');
    }
}
