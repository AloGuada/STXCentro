<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón del análisis de fletes estándar de una obra (tarjeta + parámetros de camionaje).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraFleteEstandarFactory>
 */
class ObraFleteEstandar extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_flete_estandar';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'tarjeta_id',
        'metodo',
        'grupo',
        'volumen_override',
        'kg_por_camion',
        'pzas_por_camion',
        'ml_por_pza',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metodo' => MetodoFleteEstandar::class,
            'volumen_override' => 'decimal:4',
            'kg_por_camion' => 'decimal:4',
            'pzas_por_camion' => 'integer',
            'ml_por_pza' => 'decimal:4',
            'orden' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }
}
