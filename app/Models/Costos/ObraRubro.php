<?php

namespace App\Models\Costos;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\ObraRubroFactory>
 */
class ObraRubro extends Model
{
    use HasFactory;

    protected $table = 'costos_obra_rubros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'rubro_id',
        'presupuestado',
        'acumulado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presupuestado' => 'decimal:2',
            'acumulado' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * Saldo disponible: presupuestado - acumulado. Negativo = sobregiro.
     */
    public function getDisponibleAttribute(): float
    {
        return (float) $this->presupuestado - (float) $this->acumulado;
    }

    /**
     * Porcentaje de presupuesto consumido (0..100+ si hay sobregiro).
     */
    public function getPorcentajeConsumidoAttribute(): float
    {
        $presup = (float) $this->presupuestado;
        if ($presup <= 0.0) {
            return (float) $this->acumulado > 0 ? 100.0 : 0.0;
        }

        return ((float) $this->acumulado / $presup) * 100.0;
    }

    /**
     * Estado de alerta basado en el porcentaje consumido:
     * - sobregiro: > 100%
     * - critico: >= umbral_alerta_porcentaje (default 90)
     * - normal: < umbral
     */
    public function getEstadoAlertaAttribute(): string
    {
        // Caso especial: sin presupuesto pero con gasto = sobregiro
        if ((float) $this->presupuestado <= 0.0 && (float) $this->acumulado > 0) {
            return 'sobregiro';
        }

        $pct = $this->porcentaje_consumido;
        $umbral = (int) config('costos.umbral_alerta_porcentaje', 90);

        return match (true) {
            $pct > 100.0 => 'sobregiro',
            $pct >= $umbral => 'critico',
            default => 'normal',
        };
    }
}
