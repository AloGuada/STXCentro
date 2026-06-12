<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de rendimiento por fase dentro de una sección de montaje.
 * Días = cantidad / rendimiento (guarda contra rendimiento = 0).
 *
 * @use HasFactory<\Database\Factories\Cotiz\SeccionFaseRendimientoFactory>
 */
class SeccionFaseRendimiento extends Model
{
    use HasFactory;

    protected $table = 'cotiz_seccion_fase_rendimiento';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seccion_id',
        'fase_id',
        'concepto',
        'largo_pza',
        'cantidad',
        'rendimiento',
        'jornales',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'rendimiento' => 'decimal:4',
            'jornales' => 'decimal:4',
        ];
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(SeccionMontaje::class, 'seccion_id');
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseMontaje::class, 'fase_id');
    }
}
