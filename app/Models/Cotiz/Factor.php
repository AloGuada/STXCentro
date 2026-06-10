<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Cotiz\FactorFactory>
 */
class Factor extends Model
{
    use HasFactory;

    protected $table = 'cotiz_factores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'insumo_id',
        'formula',
        'descripcion',
        'categoria_tarjeta_id',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function categoriaTarjeta(): BelongsTo
    {
        return $this->belongsTo(CategoriaTarjeta::class, 'categoria_tarjeta_id');
    }
}
