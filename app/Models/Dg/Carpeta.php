<?php

namespace App\Models\Dg;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carpeta extends Model
{
    use HasFactory;

    protected $table = 'dg_carpetas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
        'orden',
    ];

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'carpeta_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'dg_carpeta_usuario', 'carpeta_id', 'usuario_id')
            ->withPivot('puede_escribir')
            ->withTimestamps();
    }

    public function usuarioTieneAcceso(Usuario $user): bool
    {
        if ($user->can('dg.reportes.administrar')) {
            return true;
        }

        return $this->usuarios()->where('usuario_id', $user->getKey())->exists();
    }

    public function usuarioPuedeEscribir(Usuario $user): bool
    {
        if ($user->can('dg.reportes.administrar')) {
            return true;
        }

        return $this->usuarios()
            ->where('usuario_id', $user->getKey())
            ->wherePivot('puede_escribir', true)
            ->exists();
    }
}
