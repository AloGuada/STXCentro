<?php

namespace App\Models\Costos;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Concerns\CaraDeItem;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * La cara de Compras del catálogo maestro (`Item`): con la que se cotiza y se
 * compra. Se referencia desde las partidas de requisición/OC y acumula un
 * histórico de precios desde las cotizaciones. Código, descripción y unidad
 * son copias del item; la identidad y la unicidad viven allá.
 *
 * @use HasFactory<\Database\Factories\Costos\ProductoFactory>
 */
class Producto extends Model
{
    use CaraDeItem, HasFactory;

    protected $table = 'costos_productos';

    protected static function nombreDeCara(): string
    {
        return 'producto';
    }

    /**
     * En qué se mide y en qué se compra. Es una lista y no un catálogo con tabla
     * porque no le cuelga nada: nadie edita una unidad, se agregan de tarde en
     * tarde y el alta las valida contra esto mismo que valida la carga inicial.
     *
     * @var list<string>
     */
    public const UNIDADES = ['PZA', 'KG', 'LTS', 'MTS', 'PAR', 'CTO', 'SRV'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'codigo',
        'codigo_barras',
        'descripcion',
        'idsteelex',
        'area_id',
        'unidad',
        'tipo',
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
            'se_controla_por_pieza' => 'boolean',
            'requiere_verificacion' => 'boolean',
            'stock_minimo' => 'decimal:3',
            'clasificacion_abc' => ClasificacionAbc::class,
        ];
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

    /**
     * El artículo con el que Almacén guarda este producto, si es que lo guarda.
     * Null quiere decir que no lleva kardex: un servicio, un flete, o algo que
     * nadie ha clasificado todavía.
     *
     * Es uno a lo más, garantizado por un índice único parcial: dos artículos
     * sobre el mismo producto partirían su existencia en dos renglones.
     *
     * @return HasOne<Articulo, $this>
     */
    public function articulo(): HasOne
    {
        return $this->hasOne(Articulo::class, 'producto_id');
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
