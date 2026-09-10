<?php

namespace App\Policies\Drive;

use App\Models\Drive\Carpeta;
use App\Models\Usuario;

class CarpetaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->accedeAlModulo($usuario);
    }

    public function view(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->accedeAlModulo($usuario) && $carpeta->usuarioTieneAcceso($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->accedeAlModulo($usuario);
    }

    public function update(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->esDuenio($usuario, $carpeta);
    }

    public function delete(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->esDuenio($usuario, $carpeta);
    }

    /** Subir archivos: el creador, el admin y los invitados con permiso de escritura. */
    public function subirArchivo(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->accedeAlModulo($usuario) && $carpeta->usuarioPuedeEscribir($usuario);
    }

    /** Repartir la carpeta, tanto a internos como a externos: solo el creador y el admin. */
    public function gestionarAccesos(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->esDuenio($usuario, $carpeta);
    }

    private function accedeAlModulo(Usuario $usuario): bool
    {
        return $usuario->can('drive.gestionar') || $usuario->can('drive.propias');
    }

    private function esDuenio(Usuario $usuario, Carpeta $carpeta): bool
    {
        return $this->accedeAlModulo($usuario)
            && ($usuario->can('drive.gestionar') || $carpeta->esCreadaPor($usuario));
    }
}
