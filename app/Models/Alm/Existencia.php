<?php

namespace App\Models\Alm;

use App\Models\Alm\Concerns\LlenaArticuloId;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El saldo de un artículo en un almacén.
 *
 * Es caché derivada del libro de movimientos: `cantidad`, `valor` y
 * `costo_promedio` los escribe **sólo** `App\Services\Alm\AlmacenLedger`. Nadie
 * más hace `increment`, `decrement` ni `update` sobre ellas — si el saldo se
 * mueve sin dejar asiento, el kardex deja de poder explicar de dónde salió.
 *
 * `ubicacion_id` sí se escribe desde fuera: dónde está guardado no es un hecho
 * contable, es acomodo.
 *
 * @use HasFactory<\Database\Factories\Alm\ExistenciaFactory>
 */
class Existencia extends Model
{
    use HasFactory, LlenaArticuloId;

    protected $table = 'alm_existencias';

    /**
     * Sin `cantidad`, `valor` ni `costo_promedio`: no son asignables en masa a
     * propósito, para que un `update()` distraído no pueda tocarlas.
     *
     * @var list<string>
     */
    protected $fillable = [
        'almacen_id',
        'producto_id',
        'articulo_id',
        'ubicacion_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo_promedio' => 'decimal:4',
            'valor' => 'decimal:4',
            'ultimo_movimiento_at' => 'datetime',
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
     * @return HasMany<Movimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * La partición del saldo por obra. Lo **libre** no sale aquí: es lo que
     * sobra después de repartir, y por eso se calcula en vez de guardarse.
     *
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    /**
     * Cuánto de este renglón ya tiene dueño.
     *
     * Lee el alias de `withSum('asignaciones as asignado_total', 'cantidad')` si
     * el llamador lo trajo —que es lo que hace la pantalla de existencias para
     * no pagar una consulta por fila— y sólo va a la base cuando no está.
     */
    public function asignado(): float
    {
        return (float) ($this->asignado_total ?? $this->asignaciones()->sum('cantidad'));
    }

    /**
     * Lo que cualquiera puede llevarse sin pedirle permiso a nadie.
     *
     * Puede quedar negativo si alguien dejó la existencia bajo cero con un
     * ajuste: es una señal, no un caso que haya que maquillar con un `max(0)`.
     */
    public function libre(): float
    {
        return (float) $this->cantidad - $this->asignado();
    }

    /**
     * Lo que hay de verdad. Un renglón en cero no se borra —la ubicación y el
     * costo promedio siguen valiendo para la próxima entrada—, pero tampoco
     * estorba en la pantalla de existencias.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConSaldo(Builder $query): Builder
    {
        return $query->where('cantidad', '!=', 0);
    }

    /**
     * Lo que nadie ha acomodado. Es la lista de trabajo del almacenista, y por
     * eso la pantalla la deja a la vista en vez de esconderla.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSinAcomodar(Builder $query): Builder
    {
        return $query->whereNull('ubicacion_id');
    }

    /**
     * Existencia negativa: no debería pasar, y por eso hay que poder listarla.
     * Sólo el ajuste y el reverso de una entrada cancelada pueden dejarla así.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnNegativo(Builder $query): Builder
    {
        return $query->where('cantidad', '<', 0);
    }
}
