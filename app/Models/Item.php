<?php

namespace App\Models;

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * El catálogo maestro: una identidad por insumo, compartida por todos los
 * módulos.
 *
 * Compras y Almacén siguen teniendo su renglón propio —`costos_productos` con
 * los precios, `alm_articulos` con el área, la ABC y el stock mínimo— pero ya
 * no son dos cosas que haya que emparejar: son dos caras de este mismo
 * renglón, y cada cara apunta acá con una llave **obligatoria y única**. Un
 * artículo sin producto o un producto que no encuentra a su artículo dejaron
 * de ser estados posibles.
 *
 * Aquí viven código, descripción y unidad, y aquí se decide qué es "lo mismo":
 * `descripcion_normalizada` —sin mayúsculas, sin acentos, sin dobles espacios—
 * es única entre los activos. Dos "TALADRO MAGNETICO" ya no pueden nacer, ni
 * desde la carga inicial de un almacén ni desde una requisición tecleada al
 * vuelo: la segunda vez se reutiliza la primera.
 *
 * Las copias de código, descripción y unidad que siguen en las dos caras son
 * de lectura, para no tocar hoy todas las pantallas que las leen; el maestro
 * las mantiene iguales (ver `booted()`). Quitarlas es una migración posterior.
 *
 * @use HasFactory<\Database\Factories\ItemFactory>
 */
class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'descripcion',
        'unidad',
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
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->descripcion_normalizada = CatalogoMaestro::normalizar((string) $item->descripcion);
        });

        // Lo que cambia en el maestro baja a las dos caras. Va en silencio para
        // que la cara no vuelva a subirlo (ver `Producto::booted()`), que sería
        // un ciclo.
        static::updated(function (self $item): void {
            if (! $item->wasChanged(['codigo', 'descripcion', 'unidad'])) {
                return;
            }

            $item->propagar();
        });
    }

    /**
     * Baja código, descripción y unidad a las caras que los tengan distintos.
     */
    public function propagar(): void
    {
        $valores = [
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'unidad' => $this->unidad,
        ];

        foreach ([$this->producto()->first(), $this->articulo()->first()] as $cara) {
            if ($cara === null) {
                continue;
            }

            $distintos = array_filter($valores, fn ($valor, string $campo): bool => $cara->{$campo} !== $valor, ARRAY_FILTER_USE_BOTH);

            if ($distintos !== []) {
                $cara->forceFill($distintos)->saveQuietly();
            }
        }
    }

    /**
     * La cara de Compras: con la que se cotiza y se compra.
     *
     * @return HasOne<Producto, $this>
     */
    public function producto(): HasOne
    {
        return $this->hasOne(Producto::class, 'item_id');
    }

    /**
     * La cara de Almacén: la que lleva kardex. Null es legítimo —un servicio o
     * un flete se compran pero no se guardan—.
     *
     * @return HasOne<Articulo, $this>
     */
    public function articulo(): HasOne
    {
        return $this->hasOne(Articulo::class, 'item_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * El que se llama así, sin importar mayúsculas, acentos ni espacios.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLlamado(Builder $query, string $descripcion): Builder
    {
        return $query->where('descripcion_normalizada', CatalogoMaestro::normalizar($descripcion));
    }
}
