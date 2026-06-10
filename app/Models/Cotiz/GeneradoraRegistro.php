<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de material de una generadora.
 *
 * Derivados calculados en PHP (sin columnas generadas, por paridad SQLite/PostgreSQL):
 * - `t_ml_m2`      = ancho × largo × cantidad × cant_pzas (null si falta algún factor).
 * - `kilos_reales` = t_ml_m2 × peso_lineal del insumo de origen (null si falta alguno).
 *
 * Los kilos *con merma* (que aplican la fórmula de `merma`) los computa
 * App\Services\Cotiz\MermaCalculator, no un accessor, porque requieren el evaluador.
 *
 * @use HasFactory<\Database\Factories\Cotiz\GeneradoraRegistroFactory>
 */
class GeneradoraRegistro extends Model
{
    use HasFactory;

    protected $table = 'cotiz_generadora_registros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'generadora_id',
        'material_origen_id',
        'material',
        'marca',
        'ancho',
        'largo',
        'cantidad',
        'cant_pzas',
        'peso_porcentual',
        'kilos_totales',
        'merma_id',
        'validado',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        't_ml_m2',
        'kilos_reales',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ancho' => 'decimal:6',
            'largo' => 'decimal:6',
            'cantidad' => 'decimal:6',
            'cant_pzas' => 'decimal:6',
            'peso_porcentual' => 'decimal:6',
            'kilos_totales' => 'decimal:4',
            'merma_id' => 'integer',
            'validado' => 'boolean',
        ];
    }

    public function generadora(): BelongsTo
    {
        return $this->belongsTo(Generadora::class, 'generadora_id');
    }

    public function materialOrigen(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'material_origen_id');
    }

    public function merma(): BelongsTo
    {
        return $this->belongsTo(Merma::class, 'merma_id');
    }

    /**
     * Derivado: t_ml_m2 = ancho × largo × cantidad × cant_pzas. Null si falta algún factor.
     */
    public function getTMlM2Attribute(): ?float
    {
        foreach (['ancho', 'largo', 'cantidad', 'cant_pzas'] as $campo) {
            if ($this->attributes[$campo] === null) {
                return null;
            }
        }

        return (float) $this->ancho * (float) $this->largo * (float) $this->cantidad * (float) $this->cant_pzas;
    }

    /**
     * Derivado: kilos_reales = t_ml_m2 × peso_lineal del insumo de origen. Null si falta alguno.
     */
    public function getKilosRealesAttribute(): ?float
    {
        $tMlM2 = $this->t_ml_m2;
        $pesoLineal = $this->materialOrigen?->peso_lineal;

        if ($tMlM2 === null || $pesoLineal === null) {
            return null;
        }

        return $tMlM2 * (float) $pesoLineal;
    }
}
