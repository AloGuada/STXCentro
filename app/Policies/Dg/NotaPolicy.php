<?php

namespace App\Policies\Dg;

use App\Models\Dg\Nota;
use App\Models\Usuario;

class NotaPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->can('dg.reportes.notas');
    }

    public function view(Usuario $user, Nota $nota): bool
    {
        return $user->can('dg.reportes.notas') && $nota->usuario_id === $user->getKey();
    }

    public function create(Usuario $user): bool
    {
        return $user->can('dg.reportes.notas');
    }

    public function update(Usuario $user, Nota $nota): bool
    {
        return $user->can('dg.reportes.notas') && $nota->usuario_id === $user->getKey();
    }

    public function delete(Usuario $user, Nota $nota): bool
    {
        return $user->can('dg.reportes.notas') && $nota->usuario_id === $user->getKey();
    }
}
