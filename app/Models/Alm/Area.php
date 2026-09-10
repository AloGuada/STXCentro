<?php

namespace App\Models\Alm;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Área del catálogo de almacén: a qué parte de la operación pertenece un
 * artículo.
 *
 * No confundir con `Ubicacion` —dónde está físicamente, dentro de un almacén—
 * ni con `Almacen`. El área viaja con el artículo; la ubicación, con la
 * existencia.
 *
 * @use HasFactory<\Database\Factories\Alm\AreaFactory>
 */
class Area extends Model
{
    use HasFactory;

    protected $table = 'alm_areas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
