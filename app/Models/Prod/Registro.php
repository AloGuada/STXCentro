<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producción capturada: esta pieza (QS), en este proceso, en esta fecha.
 *
 * No lleva cantidad porque un renglón es una pieza. Lo que sí varía es el
 * `porcentaje`: pagar una pieza al 60% deja 40% para liquidarse en otra semana.
 */
class Registro extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\RegistroFactory> */
    use HasFactory;

    protected $table = 'prod_registros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fecha',
        'pieza_id',
        'proceso_id',
        'grupo_trabajo_id',
        'porcentaje',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'porcentaje' => 'decimal:2',
        ];
    }

    /**
     * Fracción de pieza que consume este registro del tope: pagar una pieza al
     * 60% gasta 0.6 y deja 0.4 para liquidarse después.
     */
    public function piezasEquivalentes(): float
    {
        return round((float) $this->porcentaje / 100, 4);
    }

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }
}
