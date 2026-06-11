<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override por obra del catálogo global de un insumo. Una fila agrupa todos los campos
 * override de un par (obra, insumo); cada campo NULL = "sin override" → cae al global.
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraInsumoOverrideFactory>
 */
class ObraInsumoOverride extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_insumo_override';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'insumo_id',
        'descripcion',
        'codigo_stumis',
        'unidad_id',
        'precio_unitario',
        'peso_lineal',
        'peso_default',
        'centro_costo_id',
        'comentario',
    ];

    /**
     * Campos que constituyen un override "activo". Si todos quedan NULL/vacíos, la fila
     * se considera vacía y debe borrarse (revierte al global).
     *
     * @var list<string>
     */
    public const CAMPOS_OVERRIDE = [
        'descripcion',
        'codigo_stumis',
        'unidad_id',
        'precio_unitario',
        'peso_lineal',
        'peso_default',
        'centro_costo_id',
        'comentario',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:4',
            'peso_lineal' => 'decimal:6',
            'peso_default' => 'decimal:6',
        ];
    }

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

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }
}
