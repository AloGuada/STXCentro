<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\TipoRubroFactory>
 */
class TipoRubro extends Model
{
    use HasFactory;

    protected $table = 'costos_tipo_rubros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
    ];

    public function rubros(): HasMany
    {
        return $this->hasMany(Rubro::class, 'tipo_rubro_id');
    }
}
