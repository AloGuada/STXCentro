<?php

namespace App\Models\Costos;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Area;
use App\Models\Alm\Existencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catálogo de Costos (código + descripción + unidad). Lo
 * administran Compras y Almacén; se referencia desde las partidas de
 * requisición/OC y acumula un histórico de precios desde las cotizaciones.
 *
 * @use HasFactory<\Database\Factories\Costos\ProductoFactory>
 */
class Producto extends Model
{
    use HasFactory;

    protected $table = 'costos_productos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'codigo_barras',
        'descripcion',
        'idsteelex',
        'area_id',
        'unidad',
        'tipo',
        'controla_inventario',
        'se_controla_por_pieza',
        'requiere_verificacion',
        'stock_minimo',
        'clasificacion_abc',
        'imagen',
        'activo',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tipo' => ProductoTipo::class,
            'controla_inventario' => 'boolean',
            'se_controla_por_pieza' => 'boolean',
            'requiere_verificacion' => 'boolean',
            'stock_minimo' => 'decimal:3',
            'clasificacion_abc' => ClasificacionAbc::class,
        ];
    }

    /**
     * Los que no llevan kardex se saltan el ledger sin error: se compran y se
     * reciben, pero no hay nada que almacenar (fletes, maniobras, servicios).
     */
    public function scopeDeInventario(Builder $query): Builder
    {
        return $query->where('controla_inventario', true);
    }

    /**
     * Lo que Compras tecleó al vuelo y nadie ha clasificado: sin código y fuera
     * del inventario. Es la bandeja de entrada de la pantalla de Artículos —
     * mientras estén aquí, comprarlos no mueve existencia.
     */
    public function scopeSinClasificar(Builder $query): Builder
    {
        return $query->whereNull('codigo')->where('controla_inventario', false);
    }

    /**
     * Lo que lleva identidad individual: cada pieza con su número de serie. El
     * saldo por cantidad no cambia —una pieza suma 1—, pero aquí sí se sabe
     * quién trae cuál.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePorPieza(Builder $query): Builder
    {
        return $query->where('se_controla_por_pieza', true);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * El saldo de este artículo en cada almacén. Sumarla da la existencia total,
     * que es lo que el catálogo enseña para no tener que abrir la ficha.
     *
     * @return HasMany<Existencia, $this>
     */
    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class, 'producto_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ProductoPrecio::class)->latest('fecha');
    }

    /**
     * @return HasMany<RequisicionDetalle, $this>
     */
    public function requisicionDetalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class, 'producto_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
