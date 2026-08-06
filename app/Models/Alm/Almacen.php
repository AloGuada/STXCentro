<?php

namespace App\Models\Alm;

use App\Enums\Alm\AlmacenTipo;
use App\Models\Obra;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Almacén virtual: dónde vive el material. Con obra es un almacén de la obra
 * (montaje); sin obra es central y surte a todas.
 *
 * De aquí colgarán las existencias y el kardex; por ahora es sólo el catálogo.
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

    /** Informativo: no restringe qué almacenes ve cada quien. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** Cómo se nombra en pantalla: la clave sola se repite entre obras. */
    public function etiqueta(): string
    {
        return $this->obra ? "{$this->clave} · {$this->obra->no}" : $this->clave;
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
}
