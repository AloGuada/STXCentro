<?php

namespace App\Models\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un asiento del kardex.
 *
 * Escrito **exclusivamente** por `App\Services\Alm\AlmacenLedger`: no tiene
 * factory de escritura ni se crea desde controladores. Nunca se edita ni se
 * borra — un movimiento equivocado se corrige con otro movimiento, que es lo
 * que hace auditable el saldo.
 */
class Movimiento extends Model
{
    protected $table = 'alm_movimientos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'existencia_id',
        'almacen_id',
        'producto_id',
        'tipo',
        'cantidad',
        'saldo_antes',
        'saldo_despues',
        'costo_unitario',
        'costo_promedio_despues',
        'valor_despues',
        'documento_type',
        'documento_id',
        'referencia',
        'ubicacion_id',
        'activo_id',
        'es_reverso',
        'observaciones',
        'usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => MovimientoTipo::class,
            'cantidad' => 'decimal:4',
            'saldo_antes' => 'decimal:4',
            'saldo_despues' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'costo_promedio_despues' => 'decimal:4',
            'valor_despues' => 'decimal:4',
            'es_reverso' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Existencia, $this>
     */
    public function existencia(): BelongsTo
    {
        return $this->belongsTo(Existencia::class);
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * El documento que lo originó: entrada, salida, transferencia, ajuste o el
     * alta de una pieza. Puede venir vacío en cargas iniciales.
     *
     * @return MorphTo<Model, $this>
     */
    public function documento(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * El kardex se lee en el orden en que ocurrió, y `id` desempata lo que cayó
     * en el mismo segundo: dos movimientos del mismo documento tienen la misma
     * marca de tiempo, y ordenarlos por fecha los mostraría al azar rompiendo la
     * cadena de saldos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCronologico(Builder $query, bool $descendente = false): Builder
    {
        return $query->orderBy('id', $descendente ? 'desc' : 'asc');
    }

    /**
     * Filtros de la pantalla de kardex. Viven aquí y no en el controlador para
     * que la tabla y su exportación acoten exactamente igual: si divergen, el
     * reporte enseña renglones que la pantalla esconde.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltrados(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where('almacen_id', $id))
            ->when($filtros['producto_id'] ?? null, fn (Builder $q, $id) => $q->where('producto_id', $id))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->whereDate('created_at', '<=', $h))
            ->when($filtros['referencia'] ?? null, fn (Builder $q, $r) => $q->where('referencia', 'like', "%{$r}%"));
    }
}
