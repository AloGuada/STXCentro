<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override por obra del catálogo global de un factor. Una fila agrupa todos los campos
 * override de un par (obra, factor); cada campo NULL = "sin override" → cae al global.
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraFactorOverrideFactory>
 */
class ObraFactorOverride extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_factor_override';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'factor_id',
        'nombre',
        'insumo_id',
        'formula',
        'descripcion',
        'comentario',
    ];

    /**
     * Campos que constituyen un override "activo". Si todos quedan NULL/vacíos, la fila
     * se considera vacía y debe borrarse (revierte al global).
     *
     * @var list<string>
     */
    public const CAMPOS_OVERRIDE = [
        'nombre',
        'insumo_id',
        'formula',
        'descripcion',
        'comentario',
    ];

    public function estaVacio(): bool
    {
        foreach (self::CAMPOS_OVERRIDE as $campo) {
            $valor = $this->getAttribute($campo);
            if ($valor !== null && $valor !== '') {
                return false;
            }
        }

        return true;
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function factor(): BelongsTo
    {
        return $this->belongsTo(Factor::class, 'factor_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
