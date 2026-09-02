<?php

namespace App\Models\Alm;

use App\Enums\Alm\AlmacenTipo;
use App\Models\Obra;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Almacén virtual: dónde vive el material. Con obra es un almacén de la obra
 * (montaje); sin obra es central y surte a todas.
 *
 * De aquí cuelgan las existencias y el kardex, así que un almacén con
 * movimientos ya no se borra: se desactiva.
 */
class Almacen extends Model
{
    /** @use HasFactory<\Database\Factories\Alm\AlmacenFactory> */
    use HasFactory;

    protected $table = 'alm_almacenes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'nombre',
        'obra_id',
        'tipo',
        'responsable_id',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => AlmacenTipo::class,
            'activo' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /** Quién responde por el almacén; también le da acceso, como los asignados. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /**
     * Quiénes pueden operar este almacén sin tener `alm.almacenes.ver-todos`.
     *
     * @return BelongsToMany<Usuario, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'alm_almacen_usuarios', 'almacen_id', 'usuario_id')
            ->withTimestamps();
    }

    /** Cómo se nombra en pantalla: la clave sola se repite entre obras. */
    public function etiqueta(): string
    {
        return $this->obra ? "{$this->clave} · {$this->obra->no}" : $this->clave;
    }

    /**
     * @return HasMany<Existencia, $this>
     */
    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }

    /**
     * @return HasMany<Movimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Los que no cuelgan de una obra: surten a toda la empresa.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCentrales(Builder $query): Builder
    {
        return $query->whereNull('obra_id');
    }

    /**
     * El de planta: no cuelga de una obra, asi que lo que sale de aqui se
     * consume en el mismo domicilio y no hay obra a la cual cargarselo.
     */
    public function esCentral(): bool
    {
        return $this->obra_id === null;
    }

    /**
     * Los almacenes que este usuario puede operar.
     *
     * Con `alm.almacenes.ver-todos` los ve todos; sin él, sólo aquellos donde
     * está asignado o es el responsable. El permiso arranca dado a todo rol que
     * ya podía ver el catálogo, así que el filtro sólo muerde cuando alguien
     * decide restringir a un almacenista.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisiblesPara(Builder $query, Authorizable&Model $usuario): Builder
    {
        if ($usuario->can('alm.almacenes.ver-todos')) {
            return $query;
        }

        $id = $usuario->getKey();

        return $query->where(function (Builder $filtro) use ($id) {
            $filtro->where('responsable_id', $id)
                ->orWhereHas('usuarios', fn (Builder $u) => $u->whereKey($id));
        });
    }

    /** Atajo para autorizar una acción sobre un almacén concreto. */
    public function esVisiblePara(Authorizable&Model $usuario): bool
    {
        return static::query()->visiblesPara($usuario)->whereKey($this->getKey())->exists();
    }
}
