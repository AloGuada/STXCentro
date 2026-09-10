<?php

namespace App\Models\Drive;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carpeta extends Model
{
    use HasFactory;

    protected $table = 'drive_carpetas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function externos(): BelongsToMany
    {
        return $this->belongsToMany(Externo::class, 'drive_carpeta_accesos', 'carpeta_id', 'externo_id')
            ->withTimestamps();
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'drive_carpeta_usuario', 'carpeta_id', 'usuario_id')
            ->withPivot('puede_escribir')
            ->withTimestamps();
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class, 'carpeta_id');
    }

    public function esCreadaPor(Usuario $usuario): bool
    {
        return $this->usuario_id === $usuario->getKey();
    }

    public function usuarioTieneAcceso(Usuario $usuario): bool
    {
        if ($usuario->can('drive.gestionar') || $this->esCreadaPor($usuario)) {
            return true;
        }

        return $this->usuarios()->where('usuario_id', $usuario->getKey())->exists();
    }

    public function usuarioPuedeEscribir(Usuario $usuario): bool
    {
        if ($usuario->can('drive.gestionar') || $this->esCreadaPor($usuario)) {
            return true;
        }

        return $this->usuarios()
            ->where('usuario_id', $usuario->getKey())
            ->wherePivot('puede_escribir', true)
            ->exists();
    }

    /**
     * Las carpetas que el usuario puede ver: todas si administra el Drive,
     * si no las suyas más las que le compartieron.
     */
    public function scopeVisiblesPara(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->can('drive.gestionar')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($usuario) {
            $q->where('usuario_id', $usuario->getKey())
                ->orWhereHas('usuarios', fn (Builder $u) => $u->where('usuario_id', $usuario->getKey()));
        });
    }
}
