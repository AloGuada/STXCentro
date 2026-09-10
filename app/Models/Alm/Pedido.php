<?php

namespace App\Models\Alm;

use App\Enums\Alm\PedidoEstatus;
use App\Enums\Alm\ProductoTipo;
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

/**
 * Lo que un área le pide a un almacén, folio `PED`.
 *
 * Se llama pedido y no requisición para no chocar con la requisición de compra
 * de Costos, que le pide material a un proveedor. Éste le pide a un almacén lo
 * que ya está en existencia.
 *
 * @use HasFactory<\Database\Factories\Alm\PedidoFactory>
 */
class Pedido extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'PED';

    protected $table = 'alm_pedidos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'almacen_id',
        'departamento_id',
        'obra_id',
        'solicitante_id',
        'recibe_nombre',
        'grupo_trabajo_id',
        'fecha',
        'fecha_requerida',
        'motivo',
        'estatus',
        'aprobado_por',
        'aprobado_at',
        'motivo_rechazo',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => PedidoEstatus::class,
            'fecha' => 'date',
            'fecha_requerida' => 'date',
            'aprobado_at' => 'datetime',
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
     * @return BelongsTo<Departamento, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    /**
     * Quien autorizó el pedido. Hoy siempre es null: el pedido nace aprobado y
     * la autorización se resuelve firmando el formato impreso. La columna y esta
     * relación existen para el día que eso deje de bastar.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por');
    }

    /**
     * @return BelongsTo<GrupoTrabajo, $this>
     */
    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }

    /**
     * @return HasMany<PedidoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    /**
     * Cómo se surte, según a dónde va el material.
     *
     * Con obra hay que llevarlo a otro domicilio, así que lo surte una
     * transferencia y la obra confirma cuando lo recibe. Sin obra el material se
     * queda en la planta y sale directo con una salida.
     */
    public function seSurteConTransferencia(): bool
    {
        return $this->obra_id !== null;
    }

    /**
     * Los que el almacén todavía debe: aprobados, suyos, y con algo pendiente.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSurtibles(Builder $query, ?int $almacenId = null): Builder
    {
        return $query
            ->where('estatus', PedidoEstatus::Aprobado)
            ->when($almacenId, fn (Builder $q, int $id) => $q->where('almacen_id', $id))
            ->whereHas('detalles', fn (Builder $d) => $d->whereColumn('cantidad_surtida', '<', 'cantidad_solicitada'));
    }

    /**
     * Los que puede surtir una transferencia: los que van a una obra.
     *
     * Los de consumo interno no salen por aquí — si el material se queda en la
     * planta no hay a dónde transferirlo, se entrega con una salida.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTransferibles(Builder $query, ?int $almacenId = null): Builder
    {
        return $query->surtibles($almacenId)->whereNotNull('obra_id');
    }

    /**
     * Los que puede surtir una salida: los que se quedan en el mismo domicilio.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSurtiblesConSalida(Builder $query, ?int $almacenId = null): Builder
    {
        return $query->surtibles($almacenId)->whereNull('obra_id');
    }

    /**
     * Los que puede surtir un préstamo: los que piden herramienta (activos,
     * con o sin serie) y todavía la deben. Vaya a obra o se quede en planta:
     * la herramienta no se consume, se presta.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSurtiblesConPrestamo(Builder $query, ?int $almacenId = null): Builder
    {
        return $query
            ->where('estatus', PedidoEstatus::Aprobado)
            ->when($almacenId, fn (Builder $q, int $id) => $q->where('almacen_id', $id))
            ->whereHas('detalles', fn (Builder $d) => $d
                ->whereColumn('cantidad_surtida', '<', 'cantidad_solicitada')
                ->whereHas('articulo', fn (Builder $a) => $a->where('tipo', ProductoTipo::Activo)));
    }

    /** Si entre lo que debe hay herramienta: eso se surte prestando, no sacando. */
    public function pideHerramienta(): bool
    {
        return $this->detalles->contains(fn (PedidoDetalle $d): bool => $d->esHerramienta() && $d->pendiente() > 0);
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
            ->when($filtros['obra_id'] ?? null, fn (Builder $q, $id) => $q->where('obra_id', $id))
            ->when($filtros['departamento_id'] ?? null, fn (Builder $q, $id) => $q->where('departamento_id', $id))
            ->when($filtros['estatus'] ?? null, fn (Builder $q, $e) => $q->where('estatus', $e))
            ->when(
                ($filtros['destino'] ?? null) === 'planta',
                fn (Builder $q) => $q->whereNull('obra_id'),
            )
            ->when(
                ($filtros['destino'] ?? null) === 'obra',
                fn (Builder $q) => $q->whereNotNull('obra_id'),
            )
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->whereLike('folio', "%{$s}%"));
    }
}
