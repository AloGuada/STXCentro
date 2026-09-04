<?php

namespace App\Models\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Models\Alm\Concerns\LlenaArticuloId;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pieza identificada: la pulidora número 7, no «las pulidoras».
 *
 * Sub-libro de identidad, no de cantidad. El saldo lo lleva `alm_existencias` y
 * cada pieza vale 1; aquí se responde cuál es cuál y en qué anda. Toda alta,
 * baja o cambio de almacén pasa por `App\Services\Alm\RegistradorPiezas`, que es
 * quien mantiene ese uno a uno con el kardex.
 *
 * @use HasFactory<\Database\Factories\Alm\ActivoFactory>
 */
class Activo extends Model
{
    use HasFactory, LlenaArticuloId;

    protected $table = 'alm_activos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'producto_id',
        'articulo_id',
        'no_serie',
        'codigo_barras',
        'marca',
        'modelo',
        'id_mantenimiento',
        'almacen_id',
        'ubicacion_id',
        'costo',
        'estatus',
        'condicion',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => ActivoEstatus::class,
            'costo' => 'decimal:4',
        ];
    }

    /**
     * Con qué guarda Almacén este renglón.
     *
     * Es la relación buena: `articulo_id` es la columna que queda cuando se
     * cierre la mudanza del catálogo. `producto()` sigue aquí sólo mientras
     * conviven las dos columnas.
     *
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * El producto de Compras. **Andamio**: se va con la columna en la fase B.
     * Lo que hoy se lea de aquí debe pasar a `articulo()`.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    /**
     * Las que siguen siendo del almacén. Es exactamente lo que tiene que sumar
     * la existencia del artículo, y el test del invariante lo comprueba.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estatus', '!=', ActivoEstatus::Baja);
    }

    /**
     * Las que se pueden prometer hoy. Cinco pulidoras con tres prestadas no son
     * cinco pulidoras que entregar.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estatus', ActivoEstatus::Disponible);
    }

    /**
     * Filtros del padrón. Viven aquí para que la pantalla y su exportación
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
            ->when($filtros['articulo_id'] ?? null, fn (Builder $q, $id) => $q->where('articulo_id', $id))
            ->when($filtros['estatus'] ?? null, fn (Builder $q, $e) => $q->where('estatus', $e))
            // Se busca por lo que trae grabado la pieza —serie, marca, modelo—,
            // que es lo que tiene enfrente quien la está buscando. La
            // descripción del artículo entra por si llegan por ahí.
            ->when($filtros['search'] ?? null, fn (Builder $q, string $s) => $q->where(
                fn (Builder $b) => $b
                    ->whereLike('no_serie', "%{$s}%")
                    ->orWhereLike('marca', "%{$s}%")
                    ->orWhereLike('modelo', "%{$s}%")
                    ->orWhereLike('id_mantenimiento', "%{$s}%")
                    ->orWhereLike('codigo_barras', "%{$s}%")
                    ->orWhereHas('articulo', fn (Builder $p) => $p
                        ->whereLike('codigo', "%{$s}%")
                        ->orWhereLike('descripcion', "%{$s}%"))
            ));
    }
}
