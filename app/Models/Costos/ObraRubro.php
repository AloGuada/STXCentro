<?php

namespace App\Models\Costos;

use App\Models\Obra;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'presupuesto_id',
        'obra_id',
        'rubro_id',
        'presupuestado',
        'acumulado',
        'apartado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presupuestado' => 'decimal:2',
            'acumulado' => 'decimal:2',
            'apartado' => 'decimal:2',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    /**
     * @deprecated Se conserva durante la transición (reporte PDF). Usar presupuesto().
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * Libro de movimientos del `acumulado`: cada cargo o reverso con saldo
     * antes y después. Escrito por {@see \App\Services\Costos\AcumuladoLedger}.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(RubroMovimiento::class, 'obra_rubro_id');
    }

    /**
     * Indica si el presupuesto de este rubro está cerrado para efectos de gasto.
     * El cierre lo determina el estatus propio del Presupuesto.
     */
    public function estaCerrado(): bool
    {
        return (bool) $this->presupuesto?->estaCerrado();
    }

    /**
     * Agrega un error a cada detalle cuyo `obra_rubro_id` no pertenezca al
     * presupuesto elegido. Cada documento apunta a un solo presupuesto.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public static function validarPertenenciaPresupuesto(Validator $validator, int $presupuestoId, array $detalles): void
    {
        if (! $presupuestoId) {
            return;
        }

        $rubroIds = collect($detalles)->pluck('obra_rubro_id')->filter()->unique();
        if ($rubroIds->isEmpty()) {
            return;
        }

        $presupuestosPorRubro = self::whereIn('id', $rubroIds)->pluck('presupuesto_id', 'id');

        foreach ($detalles as $i => $detalle) {
            $rubroId = $detalle['obra_rubro_id'] ?? null;
            if ($rubroId && (int) ($presupuestosPorRubro[$rubroId] ?? 0) !== $presupuestoId) {
                $validator->errors()->add("detalles.{$i}.obra_rubro_id", 'El centro de costos debe pertenecer al presupuesto seleccionado.');
            }
        }
    }

    /**
     * Total comprometido: ejercido (acumulado) + reservado vivo (apartado).
     * Es lo que realmente pesa contra el presupuesto.
     */
    public function getComprometidoAttribute(): float
    {
        return (float) $this->acumulado + (float) $this->apartado;
    }

    /**
     * Saldo disponible: presupuestado - ejercido - apartado. Negativo = sobregiro.
     */
    public function getDisponibleAttribute(): float
    {
        return (float) $this->presupuestado - $this->comprometido;
    }

    /**
     * Porcentaje de presupuesto comprometido (0..100+ si hay sobregiro).
     */
    public function getPorcentajeConsumidoAttribute(): float
    {
        $presup = (float) $this->presupuestado;
        if ($presup <= 0.0) {
            return $this->comprometido > 0 ? 100.0 : 0.0;
        }

        return ($this->comprometido / $presup) * 100.0;
    }

    /**
     * Estado de alerta basado en el porcentaje comprometido:
     * - sobregiro: > 100%
     * - critico: >= umbral_alerta_porcentaje (default 90)
     * - normal: < umbral
     */
    public function getEstadoAlertaAttribute(): string
    {
        // Caso especial: sin presupuesto pero con gasto = sobregiro
        if ((float) $this->presupuestado <= 0.0 && $this->comprometido > 0) {
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
