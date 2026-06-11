<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Celda de la matriz de kilos reales: una fila por (tarjeta, categoría, estructura).
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaKilosRealFactory>
 */
class TarjetaKilosReal extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_kilos_reales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'categoria_id',
        'estructura_id',
        'kilos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kilos' => 'decimal:4',
        ];
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(KilosRealesCategoria::class, 'categoria_id');
    }

    public function estructura(): BelongsTo
    {
        return $this->belongsTo(TarjetaEstructura::class, 'estructura_id');
    }
}
