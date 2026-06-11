<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Categoría de kilos reales vinculada a una tarjeta (M033/M034).
 * `porcentual` NULL = fila fija; con valor = fila porcentual (kilos = % × Σ fijas).
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaCategoriaKilosFactory>
 */
class TarjetaCategoriaKilos extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_categorias_kilos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'categoria_id',
        'orden',
        'porcentual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'porcentual' => 'decimal:6',
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
}
