<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factor vinculado a una tarjeta. `formula_override` (M031) gana sobre la fórmula de obra
 * y la global. `cantidad_manual` se usa cuando el factor no tiene fórmula efectiva.
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaFactorFactory>
 */
class TarjetaFactor extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_factores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'factor_id',
        'cantidad_manual',
        'importe',
        'validado',
        'formula_override',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_manual' => 'decimal:6',
            'importe' => 'decimal:4',
            'validado' => 'boolean',
        ];
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function factor(): BelongsTo
    {
        return $this->belongsTo(Factor::class, 'factor_id');
    }
}
