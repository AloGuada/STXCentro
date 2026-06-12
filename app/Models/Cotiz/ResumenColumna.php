<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Columna del Resumen de Proyecto (1 por tarjeta, auto-sincronizada).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ResumenColumnaFactory>
 */
class ResumenColumna extends Model
{
    use HasFactory;

    protected $table = 'cotiz_resumen_columnas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'nombre',
        'orden',
        'sueldo_mo_pza',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'sueldo_mo_pza' => 'decimal:4',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function tarjetas(): HasMany
    {
        return $this->hasMany(ResumenColumnaTarjeta::class, 'columna_id');
    }
}
