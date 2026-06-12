<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo 1:1 entre una columna del resumen y una tarjeta.
 *
 * @use HasFactory<\Database\Factories\Cotiz\ResumenColumnaTarjetaFactory>
 */
class ResumenColumnaTarjeta extends Model
{
    use HasFactory;

    protected $table = 'cotiz_resumen_columna_tarjetas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'columna_id',
        'tarjeta_id',
    ];

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ResumenColumna::class, 'columna_id');
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }
}
