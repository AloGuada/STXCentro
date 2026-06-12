<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override del coeficiente de una fila del resumen, por obra (nivel fila).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraResumenCoeficienteFactory>
 */
class ObraResumenCoeficiente extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_resumen_coeficientes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'fila_id',
        'coef',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coef' => 'decimal:6',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function fila(): BelongsTo
    {
        return $this->belongsTo(ResumenFila::class, 'fila_id');
    }
}
