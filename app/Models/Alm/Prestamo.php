<?php

namespace App\Models\Alm;

use App\Enums\Alm\PrestamoEstatus;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El resguardo, folio `PRE`: quién se llevó qué activos y hasta cuándo.
 *
 * No mueve saldo: lo prestado sigue siendo del almacén. Lo que cambia es la
 * custodia —una pieza pasa a `prestado`, un renglón por cantidad suma a
 * `existencias.prestado`— y por eso el documento vive aparte del kardex.
 *
 * @use HasFactory<\Database\Factories\Alm\PrestamoFactory>
 */
class Prestamo extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'PRE';

    protected $table = 'alm_prestamos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'almacen_id',
        'pedido_id',
        'responsable_id',
        'obra_id',
        'grupo_trabajo_id',
        'fecha_salida',
        'fecha_retorno_esperada',
        'estatus',
        'autorizado_por',
        'creado_por',
        'cerrado_en',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_salida' => 'date',
            'fecha_retorno_esperada' => 'date',
            'cerrado_en' => 'datetime',
            'estatus' => PrestamoEstatus::class,
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
     * El pedido que surte, si nació de uno.
     *
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * @return BelongsTo<GrupoTrabajo, $this>
     */
    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }

    /**
     * @return HasMany<PrestamoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PrestamoDetalle::class);
    }

    /** A dónde se lo llevaron, en una frase. */
    public function destino(): string
    {
        if ($this->obra !== null) {
            return $this->obra->no;
        }

        if ($this->grupoTrabajo !== null) {
            return $this->grupoTrabajo->descripcion;
        }

        return 'Planta';
    }

    /** Cuántas unidades siguen afuera, sumando piezas y cantidades. */
    public function pendiente(): float
    {
        return (float) $this->detalles->sum(fn (PrestamoDetalle $d): float => $d->pendiente());
    }

    public function estaAbierto(): bool
    {
        return $this->estatus === PrestamoEstatus::Abierto;
    }

    /** Sigue afuera y ya pasó la fecha en que debía volver. Sin fecha nunca vence. */
    public function estaVencido(): bool
    {
        return $this->estaAbierto()
            && $this->fecha_retorno_esperada !== null
            && $this->fecha_retorno_esperada->lt(today());
    }

    /** Días que lleva afuera (o que estuvo, si ya cerró). */
    public function diasFuera(): int
    {
        $hasta = $this->cerrado_en?->startOfDay() ?? today();

        return (int) $this->fecha_salida->startOfDay()->diffInDays($hasta);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estatus', PrestamoEstatus::Abierto);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->abiertos()->whereDate('fecha_retorno_esperada', '<', today());
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltrados(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where('almacen_id', $id))
            ->when($filtros['responsable_id'] ?? null, fn (Builder $q, $id) => $q->where('responsable_id', $id))
            ->when(($filtros['estatus'] ?? null) === 'vencidos', fn (Builder $q) => $q->vencidos())
            ->when(in_array($filtros['estatus'] ?? null, PrestamoEstatus::valores(), true), fn (Builder $q) => $q->where('estatus', $filtros['estatus']))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where(fn (Builder $w) => $w
                ->whereLike('folio', "%{$s}%")
                ->orWhereHas('responsable', fn ($r) => $r->whereLike('name', "%{$s}%"))
                ->orWhereHas('detalles.activo', fn ($a) => $a->whereLike('no_serie', "%{$s}%"))));
    }
}
