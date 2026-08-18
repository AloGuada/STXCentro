<?php

namespace App\Models\Alm;

use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * La entrega de material que se queda en el mismo domicilio, folio `SAL`.
 *
 * Inmutable: no tiene `update`. Se corrige cancelándola —lo que deja su reverso
 * en el kardex— y capturando la correcta.
 *
 * @use HasFactory<\Database\Factories\Alm\SalidaFactory>
 */
class Salida extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'SAL';

    protected $table = 'alm_salidas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'almacen_id',
        'pedido_id',
        'departamento_id',
        'obra_destino_id',
        'grupo_trabajo_id',
        'solicitante_id',
        'entregado_por',
        'recibe_nombre',
        'fecha',
        'motivo',
        'observaciones',
        'cancelada_at',
        'cancelada_por',
        'motivo_cancelacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cancelada_at' => 'datetime',
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
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<Departamento, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obraDestino(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_destino_id');
    }

    /**
     * @return BelongsTo<GrupoTrabajo, $this>
     */
    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function entregador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'entregado_por');
    }

    /**
     * @return HasMany<SalidaDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(SalidaDetalle::class);
    }

    /**
     * @return MorphMany<Movimiento, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(Movimiento::class, 'documento');
    }

    public function estaCancelada(): bool
    {
        return $this->cancelada_at !== null;
    }

    /**
     * Las que cuentan. Una salida cancelada vive —su folio y su reverso quedan—
     * pero deja de descontar del pedido y de sumar en los reportes.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiva(Builder $query): Builder
    {
        return $query->whereNull('cancelada_at');
    }

    /**
     * Filtros del listado. Viven aquí para que la pantalla y su exportación
     * acoten igual.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltradas(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where('almacen_id', $id))
            ->when($filtros['obra_id'] ?? null, fn (Builder $q, $id) => $q->where('obra_destino_id', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when(! ($filtros['ver_canceladas'] ?? false), fn (Builder $q) => $q->activa())
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where(
                fn (Builder $b) => $b->where('folio', 'like', "%{$s}%")
                    ->orWhere('recibe_nombre', 'like', "%{$s}%")
            ));
    }
}
