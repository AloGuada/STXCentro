<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override granular del coeficiente por celda (obra, fila, columna).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraResumenCeldaOverrideFactory>
 */
class ObraResumenCeldaOverride extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_resumen_celda_override';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'fila_id',
        'columna_id',
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

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ResumenColumna::class, 'columna_id');
    }
}
