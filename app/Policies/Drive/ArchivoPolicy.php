<?php

namespace App\Policies\Drive;

use App\Models\Drive\Archivo;
use App\Models\Usuario;

class ArchivoPolicy
{
    public function __construct(private CarpetaPolicy $carpetas) {}

    public function view(Usuario $usuario, Archivo $archivo): bool
    {
        return $this->carpetas->view($usuario, $archivo->carpeta);
    }

    public function delete(Usuario $usuario, Archivo $archivo): bool
    {
        return $this->carpetas->subirArchivo($usuario, $archivo->carpeta);
    }

    /**
     * Generar o revocar el link público. Es la acción más expuesta del módulo
     * —cualquiera con la URL descarga sin login—, así que pide escritura.
     */
    public function compartir(Usuario $usuario, Archivo $archivo): bool
    {
        return $this->carpetas->subirArchivo($usuario, $archivo->carpeta);
    }
}
