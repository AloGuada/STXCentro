<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Cotiz\CentroCostoFactory>
 */
class CentroCosto extends Model
{
    use HasFactory;

    protected $table = 'cotiz_centros_costos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cod_coste',
        'concepto',
    ];

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'centro_costo_id');
    }
}
