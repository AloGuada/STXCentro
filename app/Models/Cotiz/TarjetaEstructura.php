<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estructura (columna de la matriz de kilos reales) de una tarjeta.
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaEstructuraFactory>
 */
class TarjetaEstructura extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_estructuras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'nombre',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function celdas(): HasMany
    {
        return $this->hasMany(TarjetaKilosReal::class, 'estructura_id');
    }
}
