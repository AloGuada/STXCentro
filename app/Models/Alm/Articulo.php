<?php

namespace App\Models\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Exceptions\Catalogo\ItemDuplicadoException;
use App\Models\Concerns\CaraDeItem;
use App\Models\Costos\Producto;
use App\Models\Item;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que Almacén guarda: un artículo con su área, su clasificación y su lugar
 * en la bodega.
 *
 * Es la cara de Almacén del catálogo maestro (`Item`): la identidad —código,
 * descripción, unidad— vive en el item, y aquí lo que sólo le importa a la
 * bodega. `producto_id` es la cara de Compras de ese mismo item; puede ser null
 * mientras Compras no haya comprado nunca ese insumo, pero ya no es algo que
 * se "ligue" a mano: cuando el producto nace con la misma descripción, el
 * maestro los junta.
 *
 * **Existir aquí es llevar kardex.** No hay bandera que lo diga: un servicio o
 * un flete simplemente no tiene artículo, y lo que Compras teclea al vuelo
 * tampoco lo tiene hasta que alguien lo clasifica. Como booleano esa respuesta
 * se podía desincronizar del hecho que describía, y se desincronizó.
 *
 * @use HasFactory<\Database\Factories\Alm\ArticuloFactory>
 */
class Articulo extends Model
{
    use CaraDeItem {
        resolverItemAlNacer as resolverItemPorDescripcion;
    }
    use HasFactory;

    protected $table = 'alm_articulos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'producto_id',
        'codigo',
        'codigo_barras',
        'descripcion',
        'unidad',
        'idsteelex',
        'area_id',
        'tipo',
        'se_controla_por_pieza',
        'requiere_verificacion',
        'stock_minimo',
        'clasificacion_abc',
        'imagen',
        'activo',
        'creado_por',
    ];

    protected static function nombreDeCara(): string
    {
        return 'articulo';
    }

    /**
     * Cuando el artículo nace ya sabiendo su producto —la recepción de una
     * orden, el alta manual— el item es el del producto y no hay nada que
     * buscar. Cuando nace sin producto —la carga inicial de un almacén— se
     * resuelve por descripción, y si el maestro ya tenía ese insumo con su
     * producto, el artículo nace ligado a él: ésa es la liga que antes era
     * manual y opcional.
     */
    protected function resolverItemAlNacer(): Item
    {
        if ($this->producto_id !== null) {
            $item = Producto::query()->findOrFail($this->producto_id)->item()->firstOrFail();

            if ($item->articulo()->exists()) {
                throw new ItemDuplicadoException($item, 'el artículo');
            }

            // Si Almacén lo bautiza distinto de como lo tecleó Compras, manda
            // Almacén: es quien tiene el material enfrente. Sube al maestro y
            // de ahí baja al producto, para que siga habiendo un solo nombre.
            $cambios = array_filter([
                'descripcion' => trim((string) $this->descripcion),
                'unidad' => $this->unidad,
            ], fn ($valor, string $campo): bool => $valor !== null && $valor !== '' && $valor !== $item->{$campo}, ARRAY_FILTER_USE_BOTH);

            if ($cambios !== []) {
                $item->update($cambios);
            }

            $this->codigo ??= $item->codigo;

            return $item;
        }

        $item = $this->resolverItemPorDescripcion();

        $this->producto_id = $item->producto()->value('id');

        return $item;
    }

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
     * El producto de Compras con el que se cotiza y se compra este artículo.
     * Null es un estado legítimo, no un dato faltante: es material que existe en
     * la bodega y todavía no se empareja con nada del catálogo de Compras.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * El saldo de este artículo en cada almacén. Sumarla da la existencia
     * total, que es lo que el catálogo enseña para no tener que abrir la ficha.
     *
     * @return HasMany<Existencia, $this>
     */
    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class, 'articulo_id');
    }

    /**
     * Las piezas con serie, cuando el artículo se sigue una por una.
     *
     * @return HasMany<Activo, $this>
     */
    public function piezas(): HasMany
    {
        return $this->hasMany(Activo::class, 'articulo_id');
    }

    /**
     * Lo que todavía no se empareja con Compras. Es la bandeja de la pantalla de
     * ligado: material real esperando a que alguien diga con qué producto es el
     * mismo, o que confirme que es uno nuevo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSinLigar(Builder $query): Builder
    {
        return $query->whereNull('producto_id');
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
     * Los activos que **no** llevan serie: extensiones, arneses, lo que sale y
     * regresa pero nadie distingue una de otra. Se llevan como un solo renglón
     * por cantidad, y se prestan y devuelven contra la existencia.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivosPorCantidad(Builder $query): Builder
    {
        return $query
            ->where('tipo', ProductoTipo::Activo)
            ->where('se_controla_por_pieza', false);
    }

    /** Activo sin serie: un solo renglón por cantidad. Lo decide el catálogo. */
    public function esActivoPorCantidad(): bool
    {
        return $this->tipo === ProductoTipo::Activo && ! $this->se_controla_por_pieza;
    }
}
