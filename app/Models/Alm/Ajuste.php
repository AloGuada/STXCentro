<?php

namespace App\Models\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ajuste de existencias, folio `AJU`.
 *
 * Inmutable como todos los documentos de almacén: no tiene `update` ni
 * `destroy`. Un ajuste equivocado se corrige con otro ajuste, y los dos quedan
 * en el kardex — que es exactamente lo que se quiere poder auditar.
 *
 * @use HasFactory<\Database\Factories\Alm\AjusteFactory>
 */
class Ajuste extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'AJU';

    protected $table = 'alm_ajustes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'almacen_id',
        'motivo',
        'autorizado_por',
        'fecha',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'motivo' => AjusteMotivo::class,
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return HasMany<AjusteDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(AjusteDetalle::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por');
    }

    /**
     * Los asientos que dejó en el kardex. No todos los renglones generan uno:
     * un producto que no lleva kardex, o uno contado exacto, no mueve nada.
     *
     * @return MorphMany<Movimiento, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(Movimiento::class, 'documento');
    }

    /**
     * Cuánto se movió en neto. Se suma con signo porque es lo que interesa del
     * documento completo: si repuso o si bajó.
     */
    public function diferenciaNeta(): float
    {
        return (float) $this->detalles->sum('diferencia');
    }

    /**
     * Filtros del listado. Viven aquí para que la pantalla y su exportación
     * acoten igual.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltrados(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where('almacen_id', $id))
            ->when($filtros['motivo'] ?? null, fn (Builder $q, $m) => $q->where('motivo', $m))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->whereLike('folio', "%{$s}%"));
    }
}
