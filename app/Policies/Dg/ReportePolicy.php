<?php

namespace App\Policies\Dg;

use App\Models\Dg\Reporte;
use App\Models\Usuario;

class ReportePolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->can('dg.reportes.ver');
    }

    public function view(Usuario $user, Reporte $reporte): bool
    {
        // Admin/DG: acceso global via dg.reportes.ver
        // Gerente: puede ver archivos de carpetas donde está asignado (aunque no tenga ver global)
        return $reporte->carpeta->usuarioTieneAcceso($user);
    }

    public function update(Usuario $user, Reporte $reporte): bool
    {
        return $reporte->carpeta->usuarioPuedeEscribir($user);
    }

    public function delete(Usuario $user, Reporte $reporte): bool
    {
        return $reporte->carpeta->usuarioPuedeEscribir($user);
    }

    public function editarNotas(Usuario $user): bool
    {
        return $user->can('dg.reportes.notas');
    }
}
