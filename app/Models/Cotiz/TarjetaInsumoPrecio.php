<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M040: override de P.U. por tarjeta (el nivel más específico de la cadena
 * tarjeta > obra > global).
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaInsumoPrecioFactory>
 */
class TarjetaInsumoPrecio extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_insumo_precio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'insumo_id',
        'precio_unitario',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:4',
        ];
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
