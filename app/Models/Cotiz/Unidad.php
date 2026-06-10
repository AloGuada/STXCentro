<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Cotiz\UnidadFactory>
 */
class Unidad extends Model
{
    use HasFactory;

    protected $table = 'cotiz_unidades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
    ];

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'unidad_id');
    }
}
