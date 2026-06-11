<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote tarjeta ↔ generadora. Al vincular se importan los registros de la generadora
 * como tarjeta_registros.
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaGeneradoraFactory>
 */
class TarjetaGeneradora extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_generadoras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'generadora_id',
    ];

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function generadora(): BelongsTo
    {
        return $this->belongsTo(Generadora::class, 'generadora_id');
    }
}
